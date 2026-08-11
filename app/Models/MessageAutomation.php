<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAutomation extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'whatsapp_template_id', 'event_key', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }
}
