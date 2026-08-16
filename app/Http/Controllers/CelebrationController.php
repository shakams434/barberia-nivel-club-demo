<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CelebrationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CelebrationController extends Controller
{
    public function __invoke(Request $request, CelebrationService $celebrations): View
    {
        $today = now()->timezone($request->user()->business->timezone)->startOfDay();
        $query = trim((string) $request->query('q'));
        $searchResults = collect();

        if ($query !== '') {
            $searchResults = Customer::query()
                ->search($query)
                ->orderBy('name')
                ->limit(20)
                ->get();
        }

        return view('celebrations.index', [
            'today' => $today,
            'todayCelebrations' => $celebrations->forDate($today),
            'monthCelebrations' => $celebrations->forMonth($today),
            'searchResults' => $searchResults,
            'query' => $query,
        ]);
    }
}
