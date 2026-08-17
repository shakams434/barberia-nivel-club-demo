<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use BelongsToBusiness;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'business_id', 'customer_id', 'assigned_to', 'public_id', 'phone_e164',
        'contact_name', 'status', 'unread_count', 'last_message_at',
        'last_inbound_at', 'last_outbound_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function inboundMessages(): HasMany
    {
        return $this->hasMany(InboundMessage::class, 'whatsapp_conversation_id');
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }

    public function displayName(): string
    {
        return $this->customer?->name ?: ($this->contact_name ?: 'WhatsApp '.$this->maskedPhone());
    }

    public function maskedPhone(): string
    {
        return '•••• '.substr($this->phone_e164, -4);
    }

    public function sessionIsOpen(): bool
    {
        return $this->last_inbound_at?->isAfter(now()->subHours(24)) ?? false;
    }
}
