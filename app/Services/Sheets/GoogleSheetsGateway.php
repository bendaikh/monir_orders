<?php

namespace App\Services\Sheets;

interface GoogleSheetsGateway
{
    public function isConfigured(): bool;

    public function configurationError(): ?string;

    public function accountEmail(): ?string;

    /**
     * @return array{title: string, sheets: array<int, string>}
     */
    public function getSpreadsheet(string $spreadsheetId): array;

    /**
     * @return array<int, array<int, string>>
     */
    public function readValues(string $spreadsheetId, string $worksheet): array;

    /**
     * @param  array<int, string|int|float|null>  $row
     * @return array{updatedRange: string, updatedRows: int}
     */
    public function appendRow(string $spreadsheetId, string $worksheet, array $row): array;
}
