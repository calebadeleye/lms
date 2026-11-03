<?php

namespace App\Domain\Marketing\Models;

use App\Support\SimpleXlsx;
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

    public const SOURCE_LABELS = ['landing' => 'Free assessment page', 'home' => 'Homepage'];

    /**
     * Every lead as an Excel (.xlsx) file, newest first, with times in
     * Lagos time — attached to the "new sign-up" email the team receives
     * and offered as a download on the admin page.
     */
    public static function spreadsheet(): string
    {
        $tz = 'Africa/Lagos';
        $rows = static::query()->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (self $lead) => [
                $lead->full_name,
                $lead->email,
                self::SOURCE_LABELS[$lead->source] ?? ($lead->source ?? ''),
                $lead->created_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                $lead->last_requested_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                $lead->request_count,
                $lead->email_sent_at ? 'Yes' : 'No',
            ])->all();

        return SimpleXlsx::build(
            'Free assessment sign-ups',
            ['Full name', 'Email', 'Signed up from', 'First signed up (WAT)', 'Last requested (WAT)', 'Times requested', 'Assessment email sent'],
            $rows,
            [28, 34, 22, 22, 22, 16, 22],
        );
    }
}
