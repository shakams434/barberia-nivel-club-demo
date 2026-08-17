<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use BelongsToBusiness;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'business_id', 'customer_id', 'whatsapp_conversation_id', 'whatsapp_template_id', 'campaign_recipient_id',
        'public_id', 'direction', 'message_type', 'phone_e164', 'status', 'body_preview',
        'variables', 'meta_message_id', 'idempotency_key', 'attempts', 'error_code',
        'error_message', 'queued_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
    }
}
