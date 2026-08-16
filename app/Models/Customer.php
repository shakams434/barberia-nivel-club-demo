<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id', 'tier_id', 'public_id', 'name', 'gender', 'birth_date', 'anniversary_date', 'phone_raw', 'phone_e164', 'source',
        'phone_ciphertext', 'phone_hash', 'phone_last4', 'status', 'notes', 'xp_total',
        'level', 'joined_at', 'last_visit_at', 'anonymized_at',
    ];

    protected $hidden = ['phone_raw', 'phone_ciphertext', 'phone_hash'];

    protected function casts(): array
    {
        return [
            'phone_ciphertext' => 'encrypted',
            'birth_date' => 'date',
            'anniversary_date' => 'date',
            'joined_at' => 'datetime',
            'last_visit_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public function getPhoneE164Attribute(?string $storedValue): ?string
    {
        return filled($this->attributes['phone_ciphertext'] ?? null)
            ? $this->getAttribute('phone_ciphertext')
            : $storedValue;
    }

    public function setPhoneE164Attribute(?string $phone): void
    {
        if (blank($phone)) {
            $this->attributes['phone_e164'] = null;
            $this->attributes['phone_ciphertext'] = null;
            $this->attributes['phone_hash'] = null;
            $this->attributes['phone_last4'] = null;

            return;
        }

        $hash = self::phoneHash($phone);
        $this->attributes['phone_e164'] = 'enc_'.substr($hash, 0, 20);
        $this->setAttribute('phone_ciphertext', $phone);
        $this->attributes['phone_hash'] = $hash;
        $this->attributes['phone_last4'] = substr($phone, -4);
        $this->attributes['phone_raw'] = '•••• '.substr($phone, -4);
    }

    public static function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }

    public function maskedPhone(): string
    {
        return '•••• '.($this->phone_last4 ?: substr((string) $this->phone_e164, -4));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->latest('visited_at');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest();
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class)->latest('recorded_at');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(CustomerReward::class)->latest('unlocked_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class)->latest();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(WhatsAppMessage::class)->latestOfMany();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $term);

        return $query->where(function (Builder $query) use ($term, $digits): void {
            $query->where('name', 'like', '%'.$term.'%');

            if ($digits !== '') {
                $query->orWhere('phone_last4', substr($digits, -4));

                $candidates = [];
                if (strlen($digits) === 9) {
                    $candidates[] = '+51'.$digits;
                }
                if (strlen($digits) >= 10) {
                    $candidates[] = '+'.$digits;
                }

                if ($candidates !== []) {
                    $query->orWhereIn('phone_hash', array_map(self::phoneHash(...), array_unique($candidates)));
                }
            }
        });
    }

    public function progressPercent(int $xpPerLevel = 100): int
    {
        return (int) floor(($this->xp_total % $xpPerLevel) / $xpPerLevel * 100);
    }
}
