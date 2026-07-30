<?php

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{

    protected $fillable = ['enrolment_id', 'lesson_id', 'status', 'video_position_seconds', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
