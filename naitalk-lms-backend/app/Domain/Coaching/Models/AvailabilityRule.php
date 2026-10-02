<?php

namespace App\Domain\Coaching\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{

    protected $fillable = ['coach_id', 'day_of_week', 'start_time', 'end_time', 'timezone'];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
