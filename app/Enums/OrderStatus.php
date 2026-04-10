<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Confirmed  = 'confirmed';
    case Rejected   = 'rejected';
    case Assigning  = 'assigning';
    case Delivering = 'delivering';
    case Delivered  = 'delivered';
}
