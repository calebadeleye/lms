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

    /** Mirrors the frontend's resolveVideoEmbed() (src/lib/learning-types.ts)
     * — a YouTube/Vimeo/Google Drive link is a cross-origin iframe with no
     * way to read playback position, unlike a directly-hosted file. Keep
     * these patterns in sync with that function; ProgressService::markComplete()
     * needs this to know whether manual completion is the only option this
     * lesson has, or whether it should only ever complete via the
     * watch-90%-of-it position tracking a direct file gets. */
    public function hasExternalVideoEmbed(): bool
    {
        if (! $this->video_path) {
            return false;
        }

        return (bool) preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)[a-zA-Z0-9_-]{6,}#', $this->video_path)
            || (bool) preg_match('#vimeo\.com/(?:video/)?\d+#', $this->video_path)
            || (bool) preg_match('#drive\.google\.com/(?:file/d/|open\?id=|uc\?id=)[a-zA-Z0-9_-]+#', $this->video_path);
    }
}
