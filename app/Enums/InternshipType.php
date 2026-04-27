<?php

namespace App\Enums;

enum InternshipType: string
{
    case REMOTE = 'remote';
    case ONSITE = 'onsite';
    case HYBRID = 'hybrid';
}
