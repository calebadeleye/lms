<?php

namespace App\Domain\Marketing\Http\Controllers;

use App\Domain\Marketing\Models\AssessmentLead;
use App\Domain\Marketing\Notifications\FreeAssessmentNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The free personality assessment opt-in: a visitor leaves their name and
 * email, and we email them the assessment link plus an invitation to the
 * paid Find Your Career Fit course.
 */
class AssessmentLeadController extends Controller
{
    /** How many times one address can be sent the email per hour. */
    private const SENDS_PER_EMAIL_PER_HOUR = 3;

    /**
     * Public. Rate-limited per email address rather than per IP: requests
     * arrive through the Next.js proxy, so every visitor shares one IP here,
     * and the abuse worth stopping is flooding someone else's inbox.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'in:landing,home'],
            // Honeypot: hidden from people, filled in by naive bots.
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($data['website'])) {
            return response()->json(['data' => ['email_sent' => true]], 201);
        }

        $email = strtolower(trim($data['email']));
        $limiterKey = 'free-assessment:'.sha1($email);

        if (RateLimiter::tooManyAttempts($limiterKey, self::SENDS_PER_EMAIL_PER_HOUR)) {
            return response()->json(['errors' => ['email' => [
                'We have already sent the assessment to this email. Please check your inbox and spam folder.',
            ]]], 429);
        }

        RateLimiter::hit($limiterKey, 3600);

        $lead = AssessmentLead::firstOrNew(['email' => $email]);
        $lead->fill([
            'full_name' => trim($data['full_name']),
            'source' => $data['source'] ?? $lead->source,
            'last_requested_at' => now(),
        ]);
        $lead->request_count = $lead->exists ? $lead->request_count + 1 : 1;
        $lead->save();

        // A mail failure must not lose the lead or strand the visitor: report
        // it, and hand the link back so the page can show it directly.
        try {
            Notification::route('mail', [$email => $lead->full_name])->notify(new FreeAssessmentNotification($lead));
            $lead->update(['email_sent_at' => now()]);
            $sent = true;
        } catch (\Throwable $e) {
            report($e);
            $sent = false;
        }

        return response()->json(['data' => array_filter([
            'email_sent' => $sent,
            'assessment_url' => $sent ? null : FreeAssessmentNotification::assessmentUrl(),
        ], fn ($v) => $v !== null)], 201);
    }

    /** Admin list, newest first, with optional search. */
    public function index(Request $request)
    {
        $leads = $this->query($request)->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => collect($leads->items())->map(fn (AssessmentLead $lead) => $this->present($lead)),
            'meta' => ['pagination' => [
                'page' => $leads->currentPage(), 'per_page' => $leads->perPage(), 'total' => $leads->total(),
            ]],
        ]);
    }

    /** Admin CSV download of every lead (respects the search filter). */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Full name', 'Email', 'Source', 'Times requested', 'First requested', 'Last requested', 'Email sent']);
            $query->chunk(500, function ($leads) use ($out) {
                foreach ($leads as $lead) {
                    fputcsv($out, [
                        $lead->full_name, $lead->email, $lead->source, $lead->request_count,
                        $lead->created_at?->toDateTimeString(), $lead->last_requested_at?->toDateTimeString(),
                        $lead->email_sent_at ? 'yes' : 'no',
                    ]);
                }
            });
            fclose($out);
        }, 'free-assessment-leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(Request $request)
    {
        return AssessmentLead::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($w) => $w->where('full_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('last_requested_at')
            ->orderByDesc('id');
    }

    private function present(AssessmentLead $lead): array
    {
        return [
            'id' => $lead->id,
            'full_name' => $lead->full_name,
            'email' => $lead->email,
            'source' => $lead->source,
            'request_count' => $lead->request_count,
            'created_at' => $lead->created_at,
            'last_requested_at' => $lead->last_requested_at,
            'email_sent' => $lead->email_sent_at !== null,
        ];
    }
}
