<?php

namespace Database\Seeders;

use App\Models\AnalyticsEvent;
use App\Models\PrototypeCase;
use App\Models\SatisfactionResponse;
use App\Models\VisitorSession;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoAnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        $caseIds = PrototypeCase::whereIn('slug', ['posesion', 'cofopri', 'sunarp', 'herencia'])->pluck('id', 'slug');
        if ($caseIds->count() !== 4) {
            throw new \RuntimeException('Ejecuta primero PrototypeCaseSeeder para cargar los cuatro casos.');
        }

        DB::transaction(function () use ($caseIds) {
            VisitorSession::where('is_demo', true)->delete();
            $rangeStart = now()->month(11)->day(13)->startOfDay();
            $rangeEnd = now()->subMinutes(6);
            if ($rangeEnd->lessThan($rangeStart)) {
                throw new \RuntimeException('El rango de demostración debe comenzar el 13 de noviembre o antes de la fecha actual.');
            }
            $spanSeconds = $rangeStart->diffInSeconds($rangeEnd);
            $surveyStart = Carbon::parse('2026-11-26 00:00:00');
            $surveyEnd = Carbon::parse('2026-12-02 23:59:59');
            $surveySpanSeconds = $surveyStart->diffInSeconds($surveyEnd);

            for ($i = 0; $i < 59; $i++) {
                $startedAt = $rangeStart->copy()->addSeconds((int) floor($spanSeconds * $i / 58));
                $device = $i % 10 < 6 ? 'mobile' : ($i % 10 < 9 ? 'desktop' : 'tablet');
                $browser = $i % 10 < 6 ? 'Chrome' : ($i % 10 < 8 ? 'Edge' : ($i % 10 === 8 ? 'Safari' : 'Firefox'));
                $session = VisitorSession::create([
                    'session_key' => (string) Str::uuid(),
                    'device' => $device,
                    'browser' => $browser,
                    'landing_page' => '/',
                    'started_at' => $startedAt,
                    'last_seen_at' => $startedAt->copy()->addMinutes(2 + ($i % 4)),
                    'duration_seconds' => 45 + (($i * 37) % 500),
                    'is_demo' => true,
                ]);

                $this->event($session, 'session_start', null, $startedAt);
                $this->event($session, 'page_view', null, $startedAt->copy()->addSeconds(1));

                $case = ['posesion', 'cofopri', 'sunarp', 'herencia'][$i % 4];
                $caseId = $caseIds[$case];
                $at = $startedAt->copy()->addSeconds(15 + ($i % 40));
                $this->event($session, 'case_view', $caseId, $at);
                $this->event($session, 'case_select', $caseId, $at->copy()->addSeconds(2));

                if ($i % 5 !== 0) {
                    $this->event($session, 'requirements_view', $caseId, $at->copy()->addSeconds(25));
                }
                if ($i % 4 !== 0) {
                    $this->event($session, 'steps_view', $caseId, $at->copy()->addSeconds(40));
                }
                $requirementsTotal = ['posesion' => 7, 'cofopri' => 6, 'sunarp' => 3, 'herencia' => 3][$case];
                $this->event($session, 'checklist_start', $caseId, $at->copy()->addSeconds(55));
                if ($i % 3 !== 0) {
                    $this->event($session, 'checklist_progress', $caseId, $at->copy()->addSeconds(70), ['checked' => (string) max(1, $requirementsTotal - 1), 'total' => (string) $requirementsTotal]);
                }
                $this->event($session, 'checklist_progress', $caseId, $at->copy()->addSeconds(85), ['checked' => (string) $requirementsTotal, 'total' => (string) $requirementsTotal]);
                $this->event($session, 'checklist_complete', $caseId, $at->copy()->addSeconds(100));
                if ($i % 7 === 0) {
                    $this->event($session, 'help_click', $caseId, $at->copy()->addSeconds(110));
                }

                $rating = [5, 4, 4, 5, 3, 4, 5, 2, 4, 5][$i % 10];
                SatisfactionResponse::create([
                    'visitor_session_id' => $session->id,
                    'prototype_case_id' => $caseId,
                    'rating' => $rating,
                    'is_demo' => true,
                    'responded_at' => $surveyStart->copy()->addSeconds((int) floor($surveySpanSeconds * $i / 58)),
                ]);
            }
        });
    }

    private function event(VisitorSession $session, string $type, ?int $caseId, $at, ?array $metadata = null): void
    {
        AnalyticsEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'visitor_session_id' => $session->id,
            'event_type' => $type,
            'prototype_case_id' => $caseId,
            'page' => '/',
            'duration_seconds' => null,
            'metadata' => $metadata,
            'created_at' => $at,
        ]);
    }
}
