<?php

namespace App\Services\Api;

use App\Enums\TransferStatus;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\User;
use App\Support\OrderNormalizer;
use Illuminate\Support\Collection;
use Throwable;

class ApiOrderSender
{
    public const UNMAPPED = 'erreur: produit non mappé';

    public function __construct(private SimonExpressClient $client, private CityMatcher $cities) {}

    public static function phone(?string $value): ?string
    {
        $digits = OrderNormalizer::phone($value, 9);

        return $digits === null ? null : '+'.config('simon_express.phone_prefix', '255').$digits;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{transferred: int, duplicates: int, failed: int}
     */
    public function transferBatch(PartnerService $service, Collection $orders, ?User $user = null): array
    {
        $service->loadMissing(['productMappings', 'cityMappings']);
        $stats = ['transferred' => 0, 'duplicates' => 0, 'failed' => 0];

        foreach ($orders as $order) {
            $order->refresh()->load('items');

            if (filled($order->parcel_code)) {
                continue; // already sent: never resend
            }

            $error = null;
            $payload = $this->payload($service, $order, $error);

            if ($payload === null) {
                $this->fail($service, $order, $user, $error);
                $stats['failed']++;

                continue;
            }

            try {
                $response = $this->client->addLead($service, $payload);
                $body = $response->json() ?? ['raw' => mb_substr($response->body(), 0, 2000)];
            } catch (Throwable $exception) {
                $this->fail($service, $order, $user, 'erreur réseau : '.$exception->getMessage(), null, $payload);
                $stats['failed']++;

                continue;
            }

            if ($response->successful() && ($body['success'] ?? false) === true && filled($body['parcel_code'] ?? null)) {
                $order->update([
                    'parcel_code' => (string) $body['parcel_code'],
                    'transfer_status' => TransferStatus::Transferred,
                    'transferred_at' => now(),
                    'api_error' => null,
                ]);
                $this->log($service, $order, $user, 'success', (string) ($body['message'] ?? 'Colis créé'), ['request' => $payload, 'response' => $body]);
                $stats['transferred']++;
            } else {
                $this->fail($service, $order, $user, 'erreur API (HTTP '.$response->status().') : '.mb_substr(json_encode($body, JSON_UNESCAPED_UNICODE), 0, 500), $body, $payload);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    public function payload(PartnerService $service, Order $order, ?string &$error = null): ?array
    {
        $item = $order->items->first();
        $product = OrderNormalizer::product($item?->name);
        $mapping = $service->productMappings->first(fn ($m) => OrderNormalizer::product($m->source_product) === $product);

        if ($mapping === null || $product === '') {
            $error = self::UNMAPPED.($item?->name ? ' ('.$item->name.')' : '');

            return null;
        }

        $city = $this->cities->resolve($service, $order->city, $order->address);
        $phone = self::phone($order->phone);

        foreach (['nom' => $order->customer_name, 'téléphone' => $phone, 'ville' => $city, 'adresse' => $order->address ?: $order->city] as $label => $value) {
            if (blank($value)) {
                $error = 'erreur: '.$label.' manquant(e)';

                return null;
            }
        }

        $quantity = max(1, (int) round((float) ($item->quantity ?? 1)));
        $total = $mapping->price_override !== null
            ? (float) $mapping->price_override * ($service->api_price_mode === 'unit' ? 1 : $quantity)
            : (float) ($order->total_amount ?? $item->line_total ?? 0);
        $price = $service->api_price_mode === 'unit'
            ? ($mapping->price_override !== null ? (float) $mapping->price_override : round($total / $quantity, 2))
            : $total;

        return [
            'receiver' => $order->customer_name,
            'phone' => $phone,
            'city' => $city,
            'address' => $order->address ?: $order->city,
            'country' => config('simon_express.country', 'Tanzania'),
            'items' => [[
                'sku' => $mapping->sku,
                'quantity' => $quantity,
                'price' => floor($price) == $price ? (int) $price : $price,
            ]],
        ];
    }

    private function fail(PartnerService $service, Order $order, ?User $user, string $message, ?array $body = null, ?array $payload = null): void
    {
        $order->update(['transfer_status' => TransferStatus::Failed, 'api_error' => $message]);
        $this->log($service, $order, $user, 'failed', $message, ['request' => $payload, 'response' => $body]);
    }

    private function log(PartnerService $service, Order $order, ?User $user, string $status, string $message, array $payload): void
    {
        $order->transferLogs()->create([
            'service_id' => $service->id,
            'user_id' => $user?->id,
            'status' => $status,
            'attempt' => $order->transferLogs()->count() + 1,
            'destination_spreadsheet_id' => 'api:simon_express',
            'destination_worksheet' => 'add_lead',
            'message' => mb_substr($message, 0, 1000),
            'response_payload' => $payload,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
