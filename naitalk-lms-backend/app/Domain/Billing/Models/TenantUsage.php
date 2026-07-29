<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantUsage extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_usage';

    protected $fillable = ['metric_key', 'value', 'recorded_for'];

    protected $casts = [
        'recorded_for' => 'date',
    ];
}
