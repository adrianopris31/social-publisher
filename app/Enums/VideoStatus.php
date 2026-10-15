<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Rejected = 'rejected';
}
