<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::where('is_active', true)->pluck('name');
        return response()->json($methods);
    }
}
