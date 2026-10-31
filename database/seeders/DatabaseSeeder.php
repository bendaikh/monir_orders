<?php

namespace Database\Seeders;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\Country;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\Sheets\PartnerServiceProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@monir-orders.local'],
            [
                'name' => 'Sara El Amrani',
                'phone' => '+212 522 00 11 22',
                'password' => 'password',
                'email_verified_at' => now(),
                'rows_per_page' => 15,
            ],
        );

        $settings = Setting::query()->first() ?? new Setting;
        $settings->fill([
            'company_name' => 'Monir Orders',
            'support_email' => 'admin@monir-orders.local',
            'default_currency' => 'MAD',
            'sync_interval_minutes' => 15,
        ])->save();

        $provisioner = app(PartnerServiceProvisioner::class);
        $adminId = User::query()->where('email', 'admin@monir-orders.local')->value('id');

        $countries = [
            ['Maroc', 'MA', 'MAD', 'Validation et transfert des commandes e-commerce pour le Maroc.', true],
            ['France', 'FR', 'EUR', 'Commandes métropolitaines et partenaires de livraison.', true],
            ['Sénégal', 'SN', 'XOF', 'Opérations de Dakar et de sa région.', true],
            ['Côte d\'Ivoire', 'CI', 'XOF', 'Distribution à Abidjan et dans les villes secondaires.', true],
            ['Tunisie', 'TN', 'TND', 'Pays préparé, en attente d\'activation.', false],
        ];

        foreach ($countries as [$name, $code, $currency, $description, $active]) {
            Country::query()->updateOrCreate(
                ['code' => $code],
                compact('name', 'currency', 'description') + ['is_active' => $active],
            );
        }

        $definitions = [
            'MA' => [
                ['Atlas Livraison', 'Partenaire de livraison sur Casablanca, Rabat et Marrakech.'],
                ['Nord Express', 'Distribution sur Tanger, Tétouan et le nord du pays.'],
            ],
            'FR' => [
                ['Colis Paris', 'Préparation et remise des commandes en Île-de-France.'],
            ],
            'SN' => [
                ['Dakar Express', 'Livraison urbaine à Dakar et Pikine.'],
            ],
            'CI' => [
                ['Abidjan Distribution', 'Livraison sur Abidjan, Cocody et Marcory.'],
            ],
        ];

        $services = [];

        foreach ($definitions as $code => $rows) {
            $country = Country::query()->where('code', $code)->firstOrFail();

            foreach ($rows as [$name, $description]) {
                $service = PartnerService::query()->updateOrCreate(
                    ['country_id' => $country->id, 'name' => $name],
                    ['description' => $description, 'is_active' => true],
                );
                $provisioner->provision($service);
                $services[$name] = $service;
            }
        }

        $samples = [
            ['Atlas Livraison', 'MA-10482', 'Amina Benali', '+212 661 20 18 44', 'Casablanca', '12 rue Ibn Khaldoun, Maarif', 349.00, 'pending', 'Huile d\'argan 100 ml', 1, 349],
            ['Atlas Livraison', 'MA-10483', 'Youssef Chraibi', '+212 670 11 45 90', 'Rabat', '8 avenue Fal Ould Oumeir', 520.50, 'pending', 'Coffret safran', 2, 260.25],
            ['Atlas Livraison', 'MA-10490', 'Salma Idrissi', '+212 662 88 10 03', 'Marrakech', '27 derb Sidi Bou Amar', 189.00, 'validated', 'Poterie de Safi', 1, 189],
            ['Atlas Livraison', 'MA-10491', 'Karim El Fassi', '+212 653 40 22 17', 'Fès', '4 rue Talaa Kebira', 760.00, 'validated', 'Tapis beni ourain', 1, 760],
            ['Atlas Livraison', 'MA-10470', 'Nadia Ouazzani', '+212 666 19 33 28', 'Casablanca', '90 boulevard Zerktouni', 95.00, 'rejected', 'Savon beldi', 3, 31.67],
            ['Nord Express', 'MA-22011', 'Hicham Bennani', '+212 661 77 02 55', 'Tanger', '15 rue de Fès', 410.00, 'pending', 'Thé en vrac 1 kg', 1, 410],
            ['Nord Express', 'MA-22018', 'Imane Alaoui', '+212 672 30 64 12', 'Tétouan', '3 avenue Mohamed V', 255.00, 'rejected', 'Céramique', 1, 255],
            ['Colis Paris', 'FR-55821', 'Camille Martin', '+33 6 12 45 78 90', 'Paris', '14 rue des Martyrs', 89.90, 'pending', 'Coffret épices', 1, 89.90],
            ['Colis Paris', 'FR-55840', 'Louis Bernard', '+33 6 88 21 09 44', 'Boulogne-Billancourt', '6 rue du Château', 142.00, 'validated', 'Plateau en bois', 2, 71],
            ['Colis Paris', 'FR-55702', 'Chloé Robert', '+33 7 61 20 33 18', 'Paris', '22 boulevard de Belleville', 54.00, 'rejected', 'Bougie parfumée', 1, 54],
            ['Dakar Express', 'SN-3308', 'Fatou Ndiaye', '+221 77 512 44 10', 'Dakar', 'Sicap Liberté 6, villa 21', 18500, 'pending', 'Panier textile', 1, 18500],
            ['Dakar Express', 'SN-3314', 'Mamadou Diallo', '+221 76 220 18 63', 'Pikine', 'Rue 10, Pikine', 24000, 'validated', 'Lot de boubous', 2, 12000],
            ['Abidjan Distribution', 'CI-7710', 'Aya Kouassi', '+225 07 08 41 22 90', 'Abidjan', 'Cocody Angré, 8e tranche', 32000, 'pending', 'Jus de gingembre', 4, 8000],
            ['Abidjan Distribution', 'CI-7716', 'Koffi Assi', '+225 05 44 19 30 12', 'Abidjan', 'Marcory zone 4', 15000, 'rejected', 'Attiéké premium', 3, 5000],
        ];

        foreach ($samples as [$serviceName, $reference, $customer, $phone, $city, $address, $total, $state, $product, $quantity, $unit]) {
            $service = $services[$serviceName];
            $received = now()->subDays(match ($state) {
                'rejected' => 6,
                'validated' => 2,
                default => 1,
            })->setTime(9 + (crc32($reference) % 8), crc32($customer) % 50);

            $order = Order::query()->updateOrCreate(
                ['service_id' => $service->id, 'reference' => $reference],
                [
                    'customer_name' => $customer,
                    'phone' => $phone,
                    'city' => $city,
                    'address' => $address,
                    'total_amount' => $total,
                    'ordered_at' => $received->copy()->subHours(3),
                    'received_at' => $received,
                    'validation_status' => $state === 'pending' ? ValidationStatus::Pending : ($state === 'rejected' ? ValidationStatus::Rejected : ValidationStatus::Validated),
                    'transfer_status' => TransferStatus::None,
                    'rejection_reason' => $state === 'rejected' ? 'Adresse incomplète ou client injoignable.' : null,
                    'validated_at' => $state === 'validated' ? $received->copy()->addHours(5) : null,
                    'rejected_at' => $state === 'rejected' ? $received->copy()->addHours(4) : null,
                    'validated_by' => $state === 'pending' ? null : $adminId,
                    'source_row_number' => null,
                    'is_demo' => true,
                ],
            );

            $order->items()->updateOrCreate(
                ['order_id' => $order->id, 'name' => $product],
                [
                    'quantity' => $quantity,
                    'unit_price' => $unit,
                    'line_total' => number_format($quantity * $unit, 2, '.', ''),
                ],
            );

            if ($state !== 'pending') {
                $order->validationLogs()->updateOrCreate(
                    ['order_id' => $order->id, 'decision' => $state === 'rejected' ? 'rejected' : 'validated'],
                    [
                        'user_id' => $adminId,
                        'reason' => $state === 'rejected' ? 'Adresse incomplète ou client injoignable.' : null,
                    ],
                );
            }
        }

        foreach ($services as $service) {
            SyncLog::query()->updateOrCreate(
                ['service_id' => $service->id, 'type' => 'demo'],
                [
                    'result' => 'demo',
                    'fetched_count' => 0,
                    'created_count' => $service->orders()->count(),
                    'sent_count' => 0,
                    'error_count' => 0,
                    'skipped_count' => 0,
                    'is_demo' => true,
                    'message' => 'Jeu de démonstration affiché dans l\'interface. Aucun appel Google Sheets n\'a été effectué.',
                    'started_at' => now()->subDay(),
                    'finished_at' => now()->subDay(),
                ],
            );
        }
    }
}
