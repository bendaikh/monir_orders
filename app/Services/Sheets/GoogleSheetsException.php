<?php

namespace App\Services\Sheets;

use RuntimeException;

class GoogleSheetsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $ambiguous = false,
        public readonly int $status = 0,
    ) {
        parent::__construct($message);
    }
}
