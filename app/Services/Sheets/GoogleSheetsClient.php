<?php

namespace App\Services\Sheets;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleSheetsClient implements GoogleSheetsGateway
{
    private ?array $credentials = null;

    private bool $credentialsLoaded = false;

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    public function configurationError(): ?string
    {
        if ($this->isConfigured()) {
            return null;
        }

        $path = $this->credentialsPath();

        if ($path === null || ! is_file($path)) {
            return 'Aucun fichier d\'identifiants Google n\'a été trouvé. Renseignez GOOGLE_SHEETS_CREDENTIALS ou déposez le JSON du compte de service dans storage/app/private/google-service-account.json.';
        }

        return 'Le fichier d\'identifiants Google est illisible ou incomplet. Il doit contenir client_email et private_key.';
    }

    public function accountEmail(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    public function getSpreadsheet(string $spreadsheetId): array
    {
        $response = $this->request(
            'get',
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}",
            ['fields' => 'properties.title,sheets.properties.title'],
        );

        $sheets = [];

        foreach ($response['sheets'] ?? [] as $sheet) {
            $title = $sheet['properties']['title'] ?? null;

            if (is_string($title) && $title !== '') {
                $sheets[] = $title;
            }
        }

        return [
            'title' => (string) ($response['properties']['title'] ?? 'Sans titre'),
            'sheets' => $sheets,
        ];
    }

    public function readValues(string $spreadsheetId, string $worksheet): array
    {
        $range = rawurlencode($this->range($worksheet, 'A1:ZZ'));
        $response = $this->request(
            'get',
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}",
        );

        $rows = [];

        foreach ($response['values'] ?? [] as $row) {
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $row);
        }

        return $rows;
    }

    public function appendRow(string $spreadsheetId, string $worksheet, array $row): array
    {
        $range = rawurlencode($this->range($worksheet, 'A1'));
        $response = $this->request(
            'post',
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}:append",
            [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ],
            ['values' => [array_map(fn ($cell) => $cell === null ? '' : (string) $cell, $row)]],
        );

        $updates = $response['updates'] ?? [];

        return [
            'updatedRange' => (string) ($updates['updatedRange'] ?? ''),
            'updatedRows' => (int) ($updates['updatedRows'] ?? 0),
        ];
    }

    public function appendRows(string $spreadsheetId, string $worksheet, array $rows): array
    {
        if ($rows === []) {
            return ['updatedRange' => '', 'updatedRows' => 0];
        }

        $range = rawurlencode($this->range($worksheet, 'A1'));
        $response = $this->request(
            'post',
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}:append",
            [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ],
            ['values' => array_map(
                fn (array $row) => array_map(fn ($cell) => $cell === null ? '' : (string) $cell, $row),
                array_values($rows),
            )],
        );

        $updates = $response['updates'] ?? [];

        return [
            'updatedRange' => (string) ($updates['updatedRange'] ?? ''),
            'updatedRows' => (int) ($updates['updatedRows'] ?? 0),
        ];
    }

    public function setRowBackground(string $spreadsheetId, string $worksheet, int $row, ?array $rgb): void
    {
        $meta = $this->request(
            'get',
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}",
            ['fields' => 'sheets.properties(sheetId,title)'],
        );

        $sheetId = null;

        foreach ($meta['sheets'] ?? [] as $sheet) {
            if (($sheet['properties']['title'] ?? null) === $worksheet) {
                $sheetId = $sheet['properties']['sheetId'] ?? null;
            }
        }

        if ($sheetId === null) {
            throw new GoogleSheetsException('Onglet « '.$worksheet.' » introuvable.');
        }

        $this->request('post', "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [], [
            'requests' => [[
                'repeatCell' => [
                    'range' => ['sheetId' => $sheetId, 'startRowIndex' => $row - 1, 'endRowIndex' => $row],
                    'cell' => ['userEnteredFormat' => $rgb === null ? (object) [] : ['backgroundColor' => $rgb]],
                    'fields' => 'userEnteredFormat.backgroundColor',
                ],
            ]],
        ]);
    }

    private function range(string $worksheet, string $cells): string
    {
        $worksheet = str_replace("'", "''", $worksheet);

        return "'{$worksheet}'!{$cells}";
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $query = [], ?array $body = null): array
    {
        try {
            $pending = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(25)
                ->connectTimeout(10);

            $response = $method === 'get'
                ? $pending->get($url, $query)
                : $pending->withQueryParameters($query)->post($url, $body ?? []);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException(
                'La connexion à Google Sheets a expiré ou a été interrompue.',
                ambiguous: true,
            );
        }

        if ($response->failed()) {
            $status = $response->status();
            $message = $response->json('error.message')
                ?? $response->json('error_description')
                ?? 'Erreur inattendue de l\'API Google.';

            throw new GoogleSheetsException(
                is_string($message) ? $message : 'Erreur inattendue de l\'API Google.',
                ambiguous: in_array($status, [408, 429, 500, 502, 503, 504], true),
                status: $status,
            );
        }

        return $response->json() ?? [];
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            throw new GoogleSheetsException($this->configurationError() ?? 'Identifiants Google manquants.');
        }

        $cacheKey = 'google_sheets_token_'.sha1($credentials['client_email']);

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->exchangeJwt($credentials);
        Cache::put($cacheKey, $token['access_token'], now()->addSeconds(max(60, $token['expires_in'] - 60)));

        return $token['access_token'];
    }

    /**
     * @param  array{client_email: string, private_key: string}  $credentials
     * @return array{access_token: string, expires_in: int}
     */
    private function exchangeJwt(array $credentials): array
    {
        $jwt = $this->signJwt($credentials);

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->connectTimeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);
        } catch (ConnectionException $exception) {
            throw new GoogleSheetsException('Impossible de joindre le service d\'authentification Google.', ambiguous: true);
        }

        if ($response->failed()) {
            $message = $response->json('error_description') ?? $response->json('error') ?? 'Authentification Google refusée.';

            throw new GoogleSheetsException(is_string($message) ? $message : 'Authentification Google refusée.', status: $response->status());
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if (! is_string($accessToken) || $accessToken === '') {
            throw new GoogleSheetsException('La réponse d\'authentification Google ne contient pas de jeton.');
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ];
    }

    /**
     * @param  array{client_email: string, private_key: string}  $credentials
     */
    private function signJwt(array $credentials): string
    {
        $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $now = time();
        $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;

        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if ($privateKey === false) {
            throw new GoogleSheetsException('La clé privée du compte de service est invalide.');
        }

        $signature = '';

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new GoogleSheetsException('Impossible de signer la requête Google.');
        }

        return $unsigned.'.'.$encode($signature);
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        if ($this->credentialsLoaded) {
            return $this->credentials;
        }

        $this->credentialsLoaded = true;
        $path = $this->credentialsPath();

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $email = $decoded['client_email'] ?? null;
        $key = $decoded['private_key'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($key) || $key === '') {
            return null;
        }

        return $this->credentials = [
            'client_email' => $email,
            'private_key' => $key,
        ];
    }

    private function credentialsPath(): ?string
    {
        $configured = config('services.google_sheets.credentials');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return storage_path('app/private/google-service-account.json');
    }
}
