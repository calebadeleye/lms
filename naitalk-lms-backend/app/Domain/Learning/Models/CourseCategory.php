<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id');
    }
}
