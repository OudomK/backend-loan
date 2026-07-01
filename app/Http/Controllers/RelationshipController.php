<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Relationship;

class RelationshipController extends Controller
{
    public function index()
    {
        $relationships = Relationship::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);
            
        return response()->json([
            'status' => 'success',
            'data' => $relationships
        ]);
    }
}
