<?php

namespace App\Http\Controllers;

use App\Services\SystemHealth;
use Illuminate\Contracts\View\View;

class SystemHealthController extends Controller
{
    public function index(SystemHealth $health): View
    {
        $report = $health->report();

        return view('management.health', [
            'report' => $report,
            'bytes' => fn (?int $value): string => $health->bytes($value),
        ]);
    }
}
