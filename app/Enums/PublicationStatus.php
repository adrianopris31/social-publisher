<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Published = 'published';
    case Failed = 'failed';
}
