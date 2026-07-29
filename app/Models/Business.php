<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id', 'name', 'slug', 'timezone', 'country_code', 'phone_country_code',
        'primary_color', 'secondary_color', 'logo_path', 'contact_phone',
        'contact_email', 'settings', 'active',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array', 'active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function loyaltyProgram(): HasOne
    {
        return $this->hasOne(LoyaltyProgram::class);
    }

    public function whatsappAccount(): HasOne
    {
        return $this->hasOne(WhatsAppAccount::class);
    }
}
