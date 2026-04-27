<?php

namespace App\Enums;

enum InternshipStatus: string
{
    case OPEN = 'open';
    case ARCHIVED = 'archived';
    case CLOSED = 'closed';

    
}
