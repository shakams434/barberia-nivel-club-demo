<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\CustomerReward;
use App\Models\RewardRedemption;
use App\Services\BusinessSetupService;
use App\Services\ConsentService;
use App\Services\LoyaltyEngine;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RewardRedemptionController extends Controller
{
    public function store(
        Request $request,
        string $reward,
        LoyaltyEngine $engine,
        ConsentService $consents,
        WhatsAppMessageService $messages,
        BusinessSetupService $setup,
    ): RedirectResponse {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $customerReward = CustomerReward::where('public_id', $reward)->with(['customer', 'reward'])->firstOrFail();
        $redemption = $engine->redeemReward($customerReward, $request->user(), (string) Str::uuid(), $data['note'] ?? null);

        if ($consents->hasActive($customerReward->customer, Consent::LOYALTY)) {
            $business = $request->user()->business;
            $template = $setup->ensureTemplate($business, 'loyalty_reward_redeemed');
            $message = $messages->queue(
                $customerReward->customer,
                $template,
                [$customerReward->customer->name, $customerReward->reward->name, $request->user()->business->name],
                'reward-redemption:'.$redemption->id,
                "Hola {$customerReward->customer->name}. Confirmamos el canje de {$customerReward->reward->name}.",
            );
            if ($business->whatsappAccount?->provider === 'fake' || $template->status === 'approved') {
                $messages->attemptNow($message->id, true);
            } else {
                $message->update([
                    'error_code' => 'TEMPLATE_NOT_APPROVED',
                    'error_message' => 'Aprueba la plantilla de confirmación de canje en Meta antes de reintentar.',
                ]);
            }
        }

        return back()->with('success', 'Recompensa canjeada. El XP y el nivel no cambiaron.');
    }

    public function reverse(Request $request, string $redemption, LoyaltyEngine $engine): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        $redemption = RewardRedemption::where('public_id', $redemption)->firstOrFail();
        $engine->reverseRewardRedemption(
            $redemption,
            $request->user(),
            $data['reason'],
            'reward-redemption-reversal:'.$redemption->id,
        );

        return back()->with('success', 'Canje revertido con trazabilidad completa. El XP no cambió.');
    }
}
