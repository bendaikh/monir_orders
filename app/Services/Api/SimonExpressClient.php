<?php

namespace App\Services\Api;

use App\Models\PartnerService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SimonExpressClient
{
    private function http(PartnerService $service): PendingRequest
    {
        $config = $service->api_config ?? [];
        $base = rtrim($config['base_url'] ?? config('simon_express.base_url'), '/');
        $request = Http::baseUrl($base)->acceptJson()->asJson()->timeout((int) config('simon_express.timeout', 20));

        if (($config['auth_mode'] ?? 'bearer') === 'basic') {
            if (blank($config['consumer_key'] ?? null) || blank($config['consumer_secret'] ?? null)) {
                throw new RuntimeException('Clé (ck_) et secret (cs_) Simon Express manquants.');
            }

            return $request->withBasicAuth($config['consumer_key'], $config['consumer_secret']);
        }

        if (blank($config['token'] ?? null)) {
            throw new RuntimeException('Jeton Simon Express (tk_...) manquant.');
        }

        return $request->withToken($config['token']);
    }

    /**
     * Harmless read used by "Tester la connexion".
     *
     * @return array{ok: bool, status: int|null, message: string}
     */
    public function test(PartnerService $service): array
    {
        try {
            $response = $this->http($service)->get('/get_lead', ['parcel_code' => 'TEST']);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'status' => null, 'message' => 'Connexion impossible : '.$exception->getMessage()];
        } catch (RuntimeException $exception) {
            return ['ok' => false, 'status' => null, 'message' => $exception->getMessage()];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return ['ok' => false, 'status' => $response->status(), 'message' => 'Authentification refusée ('.$response->status().'). Vérifiez la clé.'];
        }

        if ($response->serverError()) {
            return ['ok' => false, 'status' => $response->status(), 'message' => 'Erreur serveur Simon Express ('.$response->status().').'];
        }

        return ['ok' => true, 'status' => $response->status(), 'message' => 'Authentification acceptée (HTTP '.$response->status().', colis TEST '.($response->successful() ? 'lu' : 'introuvable, normal').').'];
    }

    /**
     * Sends one lead. Retries only on network errors (no response at all), never on a received response.
     */
    public function addLead(PartnerService $service, array $payload): Response
    {
        $attempts = 1 + (int) config('simon_express.retries', 2);
        $last = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $this->http($service)->post('/add_lead', $payload);
            } catch (ConnectionException $exception) {
                $last = $exception;

                if ($i < $attempts) {
                    usleep(500000 * $i);
                }
            }
        }

        throw $last;
    }
}
