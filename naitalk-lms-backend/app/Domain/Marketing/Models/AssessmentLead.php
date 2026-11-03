<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentLead extends Model
{
    protected $fillable = ['full_name', 'email', 'source', 'request_count', 'last_requested_at', 'email_sent_at'];

    protected $casts = [
        'last_requested_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    public function firstName(): string
    {
        return strtok(trim($this->full_name), ' ') ?: $this->full_name;
    }
}
