<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Translation;

class TranslationController extends Controller
{
    public function index()
    {
        $translations = Translation::all();
        $map = [];

        foreach ($translations as $item) {
            $map[$item->key] = [
                'KH' => $item->kh,
                'EN' => $item->en,
            ];
        }

        return response()->json($map);
    }
}
