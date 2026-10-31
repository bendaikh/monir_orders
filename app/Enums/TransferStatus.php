<?php

namespace App\Enums;

enum TransferStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Transferred = 'transferred';
    case Failed = 'failed';
}
