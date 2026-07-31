<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\IdType;

class IdTypeController extends Controller
{
    public function index()
    {
        $idTypes = IdType::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);
            
        return response()->json([
            'status' => 'success',
            'data' => $idTypes
        ]);
    }
}
