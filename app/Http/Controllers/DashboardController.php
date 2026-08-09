<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        return response()->json(
            app(DashboardStatsService::class)->getDesktopStats(
                $request->boolean('fresh')
            )
        );
    }
}
