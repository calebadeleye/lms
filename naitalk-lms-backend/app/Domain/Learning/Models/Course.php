<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'title', 'slug', 'excerpt', 'description', 'thumbnail_path', 'promo_video_path',
        'status', 'pricing_type', 'price_cents', 'currency', 'difficulty_level', 'tags',
        'prerequisite_course_ids', 'drip_type', 'certificate_enabled', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'prerequisite_course_ids' => 'array',
        'certificate_enabled' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructors')->withPivot('role')->withTimestamps();
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isFree(): bool
    {
        return $this->pricing_type === 'free';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function averageRating(): float
    {
        return round((float) $this->reviews()->avg('rating'), 1);
    }

    /**
     * Stored as `{tenant}/courses/{course}/thumbnail.{ext}` on the private
     * `tenants` disk — not browser-loadable directly. Root-relative rather
     * than absolute: the browser only ever talks to the Next.js app's own
     * origin, never the backend's host directly (see
     * TenantConfigController::assetUrl() for the identical reasoning with
     * branding logos). The `?v=` suffix cache-busts
     * CourseAssetController's `max-age=3600` response header after a
     * re-upload replaces the same filename.
     */
    public function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        $path = "/api/v1/course-assets/{$this->tenant_id}/{$this->id}/".basename($this->thumbnail_path);
        $version = Storage::disk('tenants')->lastModified($this->thumbnail_path);

        return $version ? "{$path}?v={$version}" : $path;
    }
}
