<?php

namespace App\Domain\Commerce\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{

    protected $fillable = [
        'user_id', 'status', 'currency', 'subtotal_cents', 'fee_cents', 'total_cents',
        'payment_mode', 'provider', 'provider_reference', 'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->idempotency_key ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function markPaid(): void
    {
        // Guarded transition — calling this twice (e.g. a redelivered
        // webhook) is a no-op, not a double-fulfillment.
        if ($this->isPaid()) {
            return;
        }

        $this->update(['status' => 'paid', 'paid_at' => now()]);
    }
}
