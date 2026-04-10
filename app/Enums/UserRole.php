<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Owner      = 'owner';
    case Delivery   = 'delivery';
    case Customer   = 'customer';
}
