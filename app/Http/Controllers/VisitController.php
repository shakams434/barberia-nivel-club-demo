<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use App\Services\LoyaltyEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function reverse(Request $request, string $visit, LoyaltyEngine $engine): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        $visit = Visit::where('public_id', $visit)->firstOrFail();
        $engine->reverseVisit($visit, $request->user(), $data['reason'], 'reversal:'.$visit->id);

        return back()->with('success', 'Atención revertida mediante un movimiento auditado.');
    }
}
