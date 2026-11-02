<?php

namespace App\Enums;

enum ValidationStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
}
