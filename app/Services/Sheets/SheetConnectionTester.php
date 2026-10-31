<?php

namespace App\Services\Sheets;

use App\Models\SheetIntegration;
use App\Models\SyncLog;
use App\Models\User;

class SheetConnectionTester
{
    public function __construct(private GoogleSheetsGateway $gateway) {}

    public function test(SheetIntegration $integration, ?User $user = null): string
    {
        $log = $this->startLog($integration, $user, 'connection_test');

        try {
            $message = $this->perform($integration);
            $this->finish($log, 'success', $message);

            return $message;
        } catch (GoogleSheetsException $exception) {
            $message = $exception->getMessage();
            $integration->update([
                'connection_status' => $this->gateway->isConfigured() ? 'error' : 'unconfigured',
                'last_tested_at' => now(),
                'last_error' => $message,
            ]);
            $this->finish($log, 'failed', $message, 1);

            throw new GoogleSheetsException($message, $exception->ambiguous, $exception->status);
        }
    }

    public function testSend(SheetIntegration $integration, ?User $user = null): string
    {
        if ($integration->direction !== 'destination') {
            throw new GoogleSheetsException('Le test d\'envoi concerne uniquement la feuille de destination.');
        }

        $log = $this->startLog($integration, $user, 'test_send');

        try {
            if (! $this->gateway->isConfigured()) {
                throw new GoogleSheetsException($this->gateway->configurationError() ?? 'Identifiants Google manquants.');
            }

            if (! $integration->isReady()) {
                throw new GoogleSheetsException('La feuille de destination n\'est pas configurée. Aucun envoi n\'a été effectué.');
            }

            $rows = $this->gateway->readValues($integration->spreadsheet_id, $integration->worksheet_name);
            $headers = $rows[0] ?? [];

            if ($headers === []) {
                throw new GoogleSheetsException('La feuille de destination ne contient pas de ligne d\'en-têtes. Aucune ligne de test n\'a été écrite.');
            }

            $service = $integration->service()->with('columnMappings')->first();
            $mapping = $service?->mappingFor('destination') ?? [];
            $referenceColumn = $mapping['reference'] ?? null;
            $headerIndex = $this->headerIndex($headers);

            if ($referenceColumn === null || ! isset($headerIndex[$this->normalize($referenceColumn)])) {
                throw new GoogleSheetsException('La colonne de référence de destination est introuvable. Aucune ligne de test n\'a été écrite.');
            }

            $reference = 'TEST-'.now()->format('YmdHis');
            $row = array_fill(0, count($headers), '');
            $row[$headerIndex[$this->normalize($referenceColumn)]] = $reference;

            foreach ($mapping as $field => $column) {
                $index = $headerIndex[$this->normalize($column)] ?? null;

                if ($index === null || $field === 'reference') {
                    continue;
                }

                $row[$index] = 'TEST DE CONNEXION';
            }

            $result = $this->gateway->appendRow($integration->spreadsheet_id, $integration->worksheet_name, $row);

            if (($result['updatedRows'] ?? 0) < 1) {
                throw new GoogleSheetsException('Google Sheets n\'a pas confirmé l\'écriture de la ligne de test.');
            }

            $integration->update([
                'connection_status' => 'connected',
                'last_tested_at' => now(),
                'last_error' => null,
            ]);

            $message = 'Ligne de test envoyée ('.$reference.'). Vous pouvez la supprimer dans la feuille de destination.';
            $log->update([
                'result' => 'success',
                'sent_count' => 1,
                'message' => $message,
                'finished_at' => now(),
            ]);

            return $message;
        } catch (GoogleSheetsException $exception) {
            $integration->update([
                'connection_status' => $this->gateway->isConfigured() ? 'error' : 'unconfigured',
                'last_tested_at' => now(),
                'last_error' => $exception->getMessage(),
            ]);
            $this->finish($log, 'failed', $exception->getMessage(), 1);

            throw $exception;
        }
    }

    private function perform(SheetIntegration $integration): string
    {
        if (! $this->gateway->isConfigured()) {
            throw new GoogleSheetsException($this->gateway->configurationError() ?? 'Identifiants Google manquants.');
        }

        if (! $integration->isReady()) {
            throw new GoogleSheetsException('Renseignez l\'identifiant du tableur et le nom de l\'onglet avant de tester la connexion.');
        }

        $spreadsheet = $this->gateway->getSpreadsheet($integration->spreadsheet_id);

        if (! in_array($integration->worksheet_name, $spreadsheet['sheets'], true)) {
            $available = $spreadsheet['sheets'] === []
                ? 'aucun onglet'
                : implode(', ', $spreadsheet['sheets']);

            throw new GoogleSheetsException('L\'onglet « '.$integration->worksheet_name.' » est introuvable. Onglets disponibles : '.$available.'.');
        }

        $this->gateway->readValues($integration->spreadsheet_id, $integration->worksheet_name);

        $integration->update([
            'connection_status' => 'connected',
            'last_tested_at' => now(),
            'last_error' => null,
        ]);

        return 'Connexion réussie au tableur « '.$spreadsheet['title'].' », onglet « '.$integration->worksheet_name.' ».';
    }

    private function startLog(SheetIntegration $integration, ?User $user, string $type): SyncLog
    {
        return SyncLog::query()->create([
            'service_id' => $integration->service_id,
            'user_id' => $user?->id,
            'type' => $type,
            'result' => 'running',
            'started_at' => now(),
        ]);
    }

    private function finish(SyncLog $log, string $result, string $message, int $errors = 0): void
    {
        $log->update([
            'result' => $result,
            'error_count' => $errors,
            'message' => $message,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function headerIndex(array $headers): array
    {
        $index = [];

        foreach ($headers as $position => $header) {
            $index[$this->normalize($header)] = $position;
        }

        return $index;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
