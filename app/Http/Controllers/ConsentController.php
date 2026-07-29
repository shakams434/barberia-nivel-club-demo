<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\Customer;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsentController extends Controller
{
    public function store(Request $request, string $customer, ConsentService $consents): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([Consent::LOYALTY, Consent::MARKETING])],
            'status' => ['required', Rule::in(['granted', 'revoked'])],
            'consent_text' => ['nullable', 'string', 'max:1000'],
        ]);
        $customer = Customer::where('public_id', $customer)->firstOrFail();
        $consents->record(
            $customer,
            $data['type'],
            $data['status'] === 'granted',
            'admin_form',
            $request->user(),
            $data['consent_text'] ?? null,
            request: $request,
        );

        return back()->with('success', 'Consentimiento registrado en el historial.');
    }
}
