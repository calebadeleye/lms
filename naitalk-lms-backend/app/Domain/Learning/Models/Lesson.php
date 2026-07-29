<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'course_module_id', 'title', 'type', 'content', 'video_path', 'duration_seconds',
        'is_preview', 'is_mandatory', 'available_after_days', 'sort_order',
    ];

    protected $casts = [
        'content' => 'array',
        'is_preview' => 'boolean',
        'is_mandatory' => 'boolean',
    ];

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class);
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }

    /** Drip gating: available immediately unless available_after_days is set,
     * in which case it unlocks N days after the given enrolment started. */
    public function isAvailableFor(Enrolment $enrolment): bool
    {
        if ($this->available_after_days === null) {
            return true;
        }

        return $enrolment->enrolled_at->addDays($this->available_after_days)->isPast();
    }
}
