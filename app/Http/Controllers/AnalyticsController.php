<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\PrototypeCase;
use App\Models\SatisfactionResponse;
use App\Models\VisitorSession;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', ['cases' => PrototypeCase::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function event(Request $request)
    {
        $data = $request->validate([
            'event_uuid' => ['required', 'uuid'],
            'session_key' => ['required', 'uuid'],
            'event_type' => ['required', 'in:page_view,session_start,case_view,case_select,requirements_view,steps_view,checklist_start,checklist_progress,checklist_complete,help_click,navigation_click,page_exit,satisfaction_response'],
            'case' => ['nullable', 'string', 'exists:prototype_cases,slug'],
            'page' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:1800'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable', 'string', 'max:180'],
        ]);

        $agent = strtolower($request->userAgent() ?? '');
        if (preg_match('/bot|crawler|spider|headless|preview|uptime|monitor/i', $agent)) {
            return response()->json(['ok' => true, 'ignored' => true], 202);
        }

        $now = now();
        $device = preg_match('/ipad|tablet|kindle|silk/i', $agent) ? 'tablet' : (preg_match('/mobile|android|iphone/i', $agent) ? 'mobile' : 'desktop');
        $browser = preg_match('/edg\//i', $agent) ? 'Edge' : (preg_match('/firefox\//i', $agent) ? 'Firefox' : (preg_match('/opr\//i', $agent) ? 'Opera' : (preg_match('/chrome\//i', $agent) ? 'Chrome' : (preg_match('/safari\//i', $agent) ? 'Safari' : 'Otro'))));
        $session = VisitorSession::firstOrCreate(['session_key' => $data['session_key']], [
            'device' => $device,
            'browser' => $browser,
            'landing_page' => $data['page'] ?? '/',
            'started_at' => $now,
            'last_seen_at' => $now,
        ]);

        $caseId = isset($data['case']) ? PrototypeCase::where('slug', $data['case'])->value('id') : null;
        $event = AnalyticsEvent::firstOrCreate(['event_uuid' => $data['event_uuid']], [
            'visitor_session_id' => $session->id,
            'event_type' => $data['event_type'],
            'prototype_case_id' => $caseId,
            'page' => isset($data['page']) ? substr(parse_url($data['page'], PHP_URL_PATH) ?: '/', 0, 255) : null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'created_at' => $now,
        ]);

        $session->last_seen_at = $now;
        if ($data['event_type'] === 'page_exit' && isset($data['duration_seconds'])) {
            $session->duration_seconds = max($session->duration_seconds, min($data['duration_seconds'], 1800));
        }
        $session->save();

        return response()->json(['ok' => true, 'event' => $event->wasRecentlyCreated], $event->wasRecentlyCreated ? 201 : 200);
    }

    public function satisfaction(Request $request)
    {
        $data = $request->validate([
            'session_key' => ['required', 'uuid'],
            'case' => ['required', 'string', 'exists:prototype_cases,slug'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'first_name' => ['required', 'string', 'max:60', 'regex:/^[\pL\pM\s\x27-]+$/u'],
            'age' => ['required', 'integer', 'min:13', 'max:120'],
        ]);

        $session = VisitorSession::firstOrCreate(['session_key' => $data['session_key']], [
            'device' => 'other', 'browser' => 'other', 'landing_page' => '/',
            'started_at' => now(), 'last_seen_at' => now(),
        ]);
        $case = PrototypeCase::where('slug', $data['case'])->where('is_active', true)->firstOrFail();
        $response = SatisfactionResponse::updateOrCreate(
            ['visitor_session_id' => $session->id, 'prototype_case_id' => $case->id],
            ['rating' => $data['rating'], 'first_name' => trim($data['first_name']), 'age' => $data['age'], 'is_demo' => $session->is_demo, 'responded_at' => now()],
        );
        AnalyticsEvent::updateOrCreate(
            ['visitor_session_id' => $session->id, 'event_type' => 'satisfaction_response', 'prototype_case_id' => $case->id],
            ['event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'page' => '/', 'metadata' => ['rating' => (string) $data['rating']], 'created_at' => now()],
        );

        return response()->json(['ok' => true], $response->wasRecentlyCreated ? 201 : 200);
    }

    private function filtered(Request $request): Builder
    {
        [$from, $to] = $this->period($request);

        return AnalyticsEvent::query()->with(['visitorSession:id,device,browser,session_key,is_demo', 'prototypeCase:id,slug,title'])
            ->whereBetween('analytics_events.created_at', [$from, $to])
            ->when($request->filled('case'), fn ($q) => $q->whereHas('prototypeCase', fn ($case) => $case->where('slug', $request->input('case'))))
            ->when($request->filled('device'), fn ($q) => $q->whereHas('visitorSession', fn ($session) => $session->where('device', $request->input('device'))));
    }

    private function period(Request $request): array
    {
        $preset = $request->input('period', 'since_nov_13');
        $today = today();
        if ($preset === 'since_nov_13') {
            // Keep the requested reporting window anchored to November 13 of this year.
            $from = $today->copy()->month(11)->day(13)->startOfDay();
            $to = $today->copy()->month(12)->day(2)->endOfDay();
            if ($today->lt($from)) {
                $to = $today->copy()->endOfDay();
            } elseif ($today->gt($to)) {
                $to = now();
            }
            return [$from, $to];
        }
        if ($preset === 'today') return [$today->copy()->startOfDay(), now()];
        if ($preset === '7d') return [$today->copy()->subDays(6)->startOfDay(), now()];
        if ($preset === '90d') return [$today->copy()->subDays(89)->startOfDay(), now()];
        if ($preset === 'this_month') return [$today->copy()->startOfMonth(), now()];
        if ($preset === 'last_month') return [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->startOfMonth()->subSecond()];
        if ($preset === 'custom' && $request->filled(['from', 'to'])) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = Carbon::parse($request->input('to'))->endOfDay();
            if ($from->gt($to) || $from->diffInDays($to) > 366) abort(422, 'El rango personalizado no puede superar 366 días.');
            return [$from, $to];
        }

        return [$today->copy()->subDays(29)->startOfDay(), now()];
    }

    public function data(Request $request)
    {
        $request->validate([
            'period' => ['nullable', 'in:since_nov_13,today,7d,30d,90d,this_month,last_month,custom'],
            'from' => ['required_if:period,custom', 'nullable', 'date'],
            'to' => ['required_if:period,custom', 'nullable', 'date', 'after_or_equal:from'],
            'case' => ['nullable', 'exists:prototype_cases,slug'],
            'device' => ['nullable', 'in:desktop,mobile,tablet,other'],
            'detail_case' => ['nullable', 'exists:prototype_cases,slug'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $events = $this->filtered($request);
        $caseFilter = $request->input('case');
        $types = ['page_view', 'session_start', 'case_view', 'case_select', 'requirements_view', 'steps_view', 'checklist_start', 'checklist_progress', 'checklist_complete', 'help_click', 'navigation_click', 'page_exit'];
        $counts = (clone $events)->select('event_type', DB::raw('COUNT(*) as total'))->groupBy('event_type')->pluck('total', 'event_type');
        $eventTotal = (clone $events)->count();
        $unique = (clone $events)->distinct('visitor_session_id')->count('visitor_session_id');
        $sessionTotal = $caseFilter ? $unique : (clone $events)->where('event_type', 'session_start')->distinct('visitor_session_id')->count('visitor_session_id');
        $starts = (clone $events)->where('event_type', 'checklist_start')->distinct('visitor_session_id')->count('visitor_session_id');
        $completed = (clone $events)->where('event_type', 'checklist_complete')->distinct('visitor_session_id')->count('visitor_session_id');
        $averageTime = (clone $events)->whereNotNull('duration_seconds')->where('duration_seconds', '>', 0)->avg('duration_seconds') ?? 0;

        $caseRows = PrototypeCase::where('is_active', true)->orderBy('sort_order')->get()->map(function ($case) use ($events, $unique, $caseFilter) {
            $q = (clone $events)->where('prototype_case_id', $case->id);
            return [
                'slug' => $case->slug,
                'title' => $case->title,
                'views' => (clone $q)->where('event_type', 'case_view')->count(),
                'selected' => (clone $q)->where('event_type', 'case_select')->distinct('visitor_session_id')->count('visitor_session_id'),
                'unique' => (clone $q)->distinct('visitor_session_id')->count('visitor_session_id'),
                'share' => $unique ? round(((clone $q)->distinct('visitor_session_id')->count('visitor_session_id') / $unique) * 100, 1) : 0,
                'requirements' => (clone $q)->where('event_type', 'requirements_view')->distinct('visitor_session_id')->count('visitor_session_id'),
                'steps' => (clone $q)->where('event_type', 'steps_view')->distinct('visitor_session_id')->count('visitor_session_id'),
                'help' => (clone $q)->where('event_type', 'help_click')->count(),
                'checklist_start' => (clone $q)->where('event_type', 'checklist_start')->distinct('visitor_session_id')->count('visitor_session_id'),
                'checklist_progress' => (clone $q)->where('event_type', 'checklist_progress')->count(),
                'checklist_complete' => (clone $q)->where('event_type', 'checklist_complete')->distinct('visitor_session_id')->count('visitor_session_id'),
                'avg_seconds' => round((clone $q)->whereNotNull('duration_seconds')->where('duration_seconds', '>', 0)->avg('duration_seconds') ?? 0),
            ];
        });

        [$from, $to] = $this->period($request);
        $satisfactionQuery = SatisfactionResponse::query()
            ->with(['visitorSession:id,is_demo', 'prototypeCase:id,slug,title'])
            ->whereBetween('responded_at', [$from, $to])
            ->when($caseFilter, fn ($q) => $q->whereHas('prototypeCase', fn ($case) => $case->where('slug', $caseFilter)))
            ->when($request->filled('device'), fn ($q) => $q->whereHas('visitorSession', fn ($session) => $session->where('device', $request->input('device'))));
        $ratingCounts = (clone $satisfactionQuery)->select('rating', DB::raw('COUNT(*) as total'))->groupBy('rating')->pluck('total', 'rating');
        $satisfaction = [
            'count' => (clone $satisfactionQuery)->count(),
            'average' => round((clone $satisfactionQuery)->avg('rating') ?? 0, 1),
            'demo_count' => (clone $satisfactionQuery)->where('is_demo', true)->count(),
            'ratings' => collect(range(1, 5))->map(fn ($rating) => ['rating' => $rating, 'count' => (int) ($ratingCounts[$rating] ?? 0)])->values(),
            'recent' => (clone $satisfactionQuery)->latest('responded_at')->get()->map(fn ($answer) => [
                'date' => $answer->responded_at->format('Y-m-d H:i:s'),
                'case' => $answer->prototypeCase?->title,
                'rating' => $answer->rating,
                'first_name' => $answer->first_name,
                'age' => $answer->age,
                'simulated' => $answer->is_demo,
            ])->values(),
        ];
        $trendTypes = $caseFilter ? ['case_view', 'case_select'] : ['page_view', 'session_start'];
        $daily = (clone $events)->whereIn('event_type', $trendTypes)
            ->select(DB::raw('DATE(created_at) as day'), 'event_type', DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT visitor_session_id) as unique_sessions'))
            ->groupBy('day', 'event_type')->orderBy('day')->get();
        $trend = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $key = $day->format('Y-m-d');
            $trend[$key] = ['date' => $key, 'visits' => 0, 'sessions' => 0, 'unique' => 0];
        }
        foreach ($daily as $row) {
            if (! isset($trend[$row->day])) continue;
            if (in_array($row->event_type, ['page_view', 'case_view'], true)) { $trend[$row->day]['visits'] = (int) $row->total; $trend[$row->day]['unique'] = (int) $row->unique_sessions; }
            if (in_array($row->event_type, ['session_start', 'case_select'], true)) $trend[$row->day]['sessions'] = (int) $row->total;
        }
        $trend = array_values($trend);

        $funnelTypes = ['page_view', 'case_select', 'requirements_view', 'steps_view', 'checklist_start', 'checklist_complete', 'help_click'];
        $funnel = collect($funnelTypes)->map(fn ($type) => ['event' => $type, 'sessions' => (clone $events)->where('event_type', $type)->distinct('visitor_session_id')->count('visitor_session_id')]);

        $behaviors = [
            'devices' => (clone $events)->join('visitor_sessions', 'analytics_events.visitor_session_id', '=', 'visitor_sessions.id')->select('visitor_sessions.device', DB::raw('COUNT(*) as total'))->groupBy('visitor_sessions.device')->pluck('total', 'device'),
            'browsers' => (clone $events)->join('visitor_sessions', 'analytics_events.visitor_session_id', '=', 'visitor_sessions.id')->select('visitor_sessions.browser', DB::raw('COUNT(*) as total'))->groupBy('visitor_sessions.browser')->pluck('total', 'browser'),
            'pages' => (clone $events)->select('page', DB::raw('COUNT(*) as total'))->whereNotNull('page')->groupBy('page')->orderByDesc('total')->limit(8)->pluck('total', 'page'),
            'actions' => (clone $events)->select('event_type', DB::raw('COUNT(*) as total'))->groupBy('event_type')->orderByDesc('total')->pluck('total', 'event_type'),
            'hours' => (clone $events)->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as total'))->groupBy('hour')->orderBy('hour')->pluck('total', 'hour'),
            'weekdays' => (clone $events)->select(DB::raw('DAYOFWEEK(created_at) as weekday'), DB::raw('COUNT(*) as total'))->groupBy('weekday')->orderBy('weekday')->pluck('total', 'weekday'),
        ];

        $detailCase = $caseFilter ?: $request->input('detail_case', 'posesion');
        $detailCaseId = PrototypeCase::where('slug', $detailCase)->value('id');
        $caseDaily = (clone $events)->when($detailCaseId, fn ($q) => $q->where('prototype_case_id', $detailCaseId))
            ->whereIn('event_type', ['case_view', 'case_select'])
            ->select(DB::raw('DATE(created_at) as day'), 'event_type', DB::raw('COUNT(*) as total'))
            ->groupBy('day', 'event_type')->orderBy('day')->get();
        $recent = (clone $events)->latest('analytics_events.created_at')->paginate(15)->through(fn ($event) => [
            'date' => $event->created_at->format('Y-m-d H:i:s'), 'event' => $event->event_type,
            'case' => $event->prototypeCase?->title, 'page' => $event->page,
            'device' => $event->visitorSession?->device, 'browser' => $event->visitorSession?->browser,
            'simulated' => (bool) $event->visitorSession?->is_demo,
            'rating' => $event->event_type === 'satisfaction_response' ? ($event->metadata['rating'] ?? null) : null,
            'duration_seconds' => $event->duration_seconds,
        ]);
        $demoSessions = (clone $events)->whereHas('visitorSession', fn ($query) => $query->where('is_demo', true))
            ->distinct('visitor_session_id')->count('visitor_session_id');

        return response()->json([
            'cards' => [
                'visits' => (int) ($counts[$caseFilter ? 'case_view' : 'page_view'] ?? 0), 'unique' => $unique, 'sessions' => $sessionTotal,
                'cases' => (int) ($counts['case_view'] ?? 0), 'interactions' => $eventTotal,
                'checklist_rate' => $starts ? round(($completed / $starts) * 100, 1) : 0,
                'avg_seconds' => (int) round($averageTime),
            ],
            'events' => $counts->toArray(), 'trend' => $trend, 'trend_mode' => $caseFilter ? 'case' : 'site', 'cases' => $caseRows,
            'funnel' => $funnel, 'behavior' => $behaviors, 'recent' => $recent,
            'demo_sessions' => $demoSessions,
            'satisfaction' => $satisfaction,
            'case_detail' => $caseRows->firstWhere('slug', $detailCase),
            'case_trend' => $caseDaily,
            'has_data' => $eventTotal > 0,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function export(Request $request, string $report = 'events'): StreamedResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:since_nov_13,today,7d,30d,90d,this_month,last_month,custom'],
            'from' => ['required_if:period,custom', 'nullable', 'date'],
            'to' => ['required_if:period,custom', 'nullable', 'date', 'after_or_equal:from'],
            'case' => ['nullable', 'exists:prototype_cases,slug'],
            'device' => ['nullable', 'in:desktop,mobile,tablet,other'],
        ]);
        abort_unless(in_array($report, ['events', 'summary', 'cases'], true), 404);
        $filename = 'reporte-' . $report . '-' . now()->format('Ymd-His') . '.csv';
        if ($report === 'summary') {
            $query = $this->filtered($request);
            [$from, $to] = $this->period($request);
            $surveyQuery = SatisfactionResponse::query()->whereBetween('responded_at', [$from, $to])
                ->when($request->filled('case'), fn ($q) => $q->whereHas('prototypeCase', fn ($case) => $case->where('slug', $request->input('case'))))
                ->when($request->filled('device'), fn ($q) => $q->whereHas('visitorSession', fn ($session) => $session->where('device', $request->input('device'))));
            $rows = [
                ['Visitas a la página', (clone $query)->where('event_type', 'page_view')->count()],
                ['Sesiones anónimas activas', (clone $query)->distinct('visitor_session_id')->count('visitor_session_id')],
                ['Visualizaciones de casos', (clone $query)->where('event_type', 'case_view')->count()],
                ['Interacciones totales', (clone $query)->count()],
                ['Sesiones simuladas (demostración)', (clone $query)->whereHas('visitorSession', fn ($session) => $session->where('is_demo', true))->distinct('visitor_session_id')->count('visitor_session_id')],
                ['Respuestas de satisfacción', (clone $surveyQuery)->count()],
                ['Satisfacción promedio (de 5)', round((clone $surveyQuery)->avg('rating') ?? 0, 1)],
                ['Respuestas demo', (clone $surveyQuery)->where('is_demo', true)->count()],
                ['Listas iniciadas', (clone $query)->where('event_type', 'checklist_start')->distinct('visitor_session_id')->count('visitor_session_id')],
                ['Listas completadas', (clone $query)->where('event_type', 'checklist_complete')->distinct('visitor_session_id')->count('visitor_session_id')],
                ['Orientaciones solicitadas', (clone $query)->where('event_type', 'help_click')->count()],
            ];
            $filename = 'resumen-' . now()->format('Ymd-His') . '.csv';
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Indicador', 'Valor'], ';');
                foreach ($rows as $row) fputcsv($out, $row, ';');
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($report === 'cases') {
            $query = $this->filtered($request);
            [$from, $to] = $this->period($request);
            $cases = PrototypeCase::where('is_active', true)->orderBy('sort_order')->get();
            $filename = 'comparacion-casos-' . now()->format('Ymd-His') . '.csv';
            return response()->streamDownload(function () use ($query, $cases, $from, $to, $request) {
                $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Caso', 'Sesiones que lo vieron', 'Sesiones demo', 'Sesiones que lo seleccionaron', 'Consultas de requisitos', 'Consultas de pasos', 'Listas iniciadas', 'Listas completadas', 'Clics de orientación', 'Respuestas de satisfacción', 'Promedio de utilidad (de 5)'], ';');
                foreach ($cases as $case) {
                    $q = (clone $query)->where('prototype_case_id', $case->id);
                    $survey = SatisfactionResponse::query()->where('prototype_case_id', $case->id)->whereBetween('responded_at', [$from, $to])
                        ->when($request->filled('device'), fn ($answer) => $answer->whereHas('visitorSession', fn ($session) => $session->where('device', $request->input('device'))));
                    fputcsv($out, [
                        $case->title,
                        (clone $q)->where('event_type', 'case_view')->distinct('visitor_session_id')->count('visitor_session_id'),
                        (clone $q)->where('event_type', 'case_view')->whereHas('visitorSession', fn ($session) => $session->where('is_demo', true))->distinct('visitor_session_id')->count('visitor_session_id'),
                        (clone $q)->where('event_type', 'case_select')->distinct('visitor_session_id')->count('visitor_session_id'),
                        (clone $q)->where('event_type', 'requirements_view')->distinct('visitor_session_id')->count('visitor_session_id'),
                        (clone $q)->where('event_type', 'steps_view')->distinct('visitor_session_id')->count('visitor_session_id'),
                        (clone $q)->where('event_type', 'checklist_start')->distinct('visitor_session_id')->count('visitor_session_id'),
                        (clone $q)->where('event_type', 'checklist_complete')->distinct('visitor_session_id')->count('visitor_session_id'),
                        (clone $q)->where('event_type', 'help_click')->count(),
                        (clone $survey)->count(),
                        round((clone $survey)->avg('rating') ?? 0, 1),
                    ], ';');
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $events = $this->filtered($request)->with('visitorSession:id,device,browser,session_key,is_demo')->orderBy('analytics_events.created_at')->cursor();

        return response()->streamDownload(function () use ($events) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha y hora', 'Origen', 'Evento', 'Caso', 'Calificación', 'Página', 'Dispositivo', 'Navegador', 'Duración (segundos)'], ';');
            foreach ($events as $event) {
                fputcsv($out, [$event->created_at->format('Y-m-d H:i:s'), $event->visitorSession?->is_demo ? 'Demo (simulado)' : 'Real', $event->event_type, $event->prototypeCase?->title, data_get($event->metadata, 'rating'), $event->page, $event->visitorSession?->device, $event->visitorSession?->browser, $event->duration_seconds], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
