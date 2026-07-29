<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantTestimonial extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['quote', 'author'];
}
