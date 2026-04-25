<?php

namespace App\Enums;

enum UserRole: string
{
    case STUDENT = 'student';
    case COMPANY = 'company';
    case ADMIN = 'admin';
}
