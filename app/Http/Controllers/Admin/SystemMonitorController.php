<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Order;
use App\Models\User;

class SystemMonitorController extends Controller
{
    public function index()
    {
        return response()->json([
            'total_owners'    => Owner::count(),
            'active_owners'   => Owner::where('status', 'active')->count(),
            'total_orders'    => Order::count(),
            'pending_orders'  => Order::where('status', 'pending')->count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_revenue'   => Order::where('status', 'delivered')->sum('total_amount'),
        ]);
    }
}
