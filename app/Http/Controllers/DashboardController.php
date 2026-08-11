<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\CustomerReward;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use App\Models\Visit;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $metrics = [
            'active_customers' => Customer::where('status', 'active')->count(),
            'daily_visits' => Visit::where('status', 'registered')->where('visited_at', '>=', now()->startOfDay())->count(),
            'new_customers' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
            'monthly_visits' => Visit::where('status', 'registered')->where('visited_at', '>=', now()->startOfMonth())->count(),
            'unlocked_rewards' => CustomerReward::where('unlocked_at', '>=', now()->startOfMonth())->count(),
            'redeemed_rewards' => RewardRedemption::where('redeemed_at', '>=', now()->startOfMonth())->count(),
            'inactive_customers' => Customer::where('status', 'active')
                ->where(fn ($query) => $query->whereNull('last_visit_at')->orWhere('last_visit_at', '<=', now()->subDays(45)))
                ->count(),
            'failed_messages' => WhatsAppMessage::whereIn('status', ['failed', 'cancelled'])->count(),
        ];

        $tierDistribution = Customer::select('tier_id', DB::raw('count(*) as total'))
            ->with('tier')
            ->groupBy('tier_id')
            ->get();
        $recentVisits = Visit::with(['customer', 'service'])->latest('visited_at')->limit(6)->get();
        $recentRedemptions = RewardRedemption::with(['customer', 'customerReward.reward'])
            ->where('status', 'completed')
            ->latest('redeemed_at')
            ->limit(4)
            ->get();
        $recentCampaigns = Campaign::withCount('recipients')->latest()->limit(3)->get();
        $account = WhatsAppAccount::first();
        $checklist = [
            ['label' => 'Servicios y XP configurados', 'done' => Service::where('active', true)->exists()],
            ['label' => 'Recompensas creadas', 'done' => Reward::where('active', true)->exists()],
            ['label' => 'Plantilla promocional aprobada', 'done' => WhatsAppTemplate::where('category', 'marketing')->where('status', 'approved')->exists()],
            ['label' => 'Canal de WhatsApp definido', 'done' => $account && in_array($account->provider, ['fake', 'meta'], true)],
        ];

        return view('dashboard', compact('metrics', 'tierDistribution', 'recentVisits', 'recentRedemptions', 'recentCampaigns', 'checklist'));
    }
}
