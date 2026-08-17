<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Str;

class WhatsAppConversationService
{
    public function forPhone(int $businessId, string $phone, ?Customer $customer = null, ?string $contactName = null): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::withoutGlobalScope('business')->firstOrCreate(
            ['business_id' => $businessId, 'phone_e164' => $phone],
            [
                'customer_id' => $customer?->id,
                'public_id' => (string) Str::uuid(),
                'contact_name' => $contactName,
                'status' => 'open',
            ],
        );

        $updates = [];
        if (! $conversation->customer_id && $customer) {
            $updates['customer_id'] = $customer->id;
        }
        if (blank($conversation->contact_name) && filled($contactName)) {
            $updates['contact_name'] = Str::limit($contactName, 120, '');
        }
        if ($updates !== []) {
            $conversation->update($updates);
        }

        return $conversation->fresh();
    }
}
