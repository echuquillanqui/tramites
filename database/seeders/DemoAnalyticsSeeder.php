<?php

namespace Database\Seeders;

use App\Models\AnalyticsEvent;
use App\Models\PrototypeCase;
use App\Models\VisitorSession;
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

            for ($i = 0; $i < 58; $i++) {
                $startedAt = now()->subDays(1 + (($i * 11) % 29))->setTime(8 + (($i * 7) % 12), ($i * 13) % 60, 0);
                $device = $i % 10 < 6 ? 'mobile' : ($i % 10 < 9 ? 'desktop' : 'tablet');
                $browser = $i % 10 < 6 ? 'Chrome' : ($i % 10 < 8 ? 'Edge' : ($i % 10 === 8 ? 'Safari' : 'Firefox'));
                $session = VisitorSession::create([
                    'session_key' => (string) Str::uuid(),
                    'device' => $device,
                    'browser' => $browser,
                    'landing_page' => '/',
                    'started_at' => $startedAt,
                    'last_seen_at' => $startedAt->copy()->addMinutes(2 + ($i % 9)),
                    'duration_seconds' => 45 + (($i * 37) % 500),
                    'is_demo' => true,
                ]);

                $this->event($session, 'session_start', null, $startedAt);
                $this->event($session, 'page_view', null, $startedAt->copy()->addSeconds(1));

                if ($i % 12 === 0) {
                    continue;
                }

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
                if ($i % 3 !== 0) {
                    $this->event($session, 'checklist_start', $caseId, $at->copy()->addSeconds(55));
                    $this->event($session, 'checklist_progress', $caseId, $at->copy()->addSeconds(70), ['checked' => (string) (1 + ($i % 3)), 'total' => '4']);
                    if ($i % 4 === 1) {
                        $this->event($session, 'checklist_complete', $caseId, $at->copy()->addSeconds(100));
                    }
                }
                if ($i % 7 === 0) {
                    $this->event($session, 'help_click', $caseId, $at->copy()->addSeconds(110));
                }
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
