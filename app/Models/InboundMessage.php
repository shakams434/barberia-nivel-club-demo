<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundMessage extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'customer_id', 'whatsapp_conversation_id', 'public_id', 'meta_message_id', 'from_phone_e164',
        'command', 'message_text', 'payload', 'status', 'processed_at', 'read_at', 'replied_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime', 'read_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }
}
