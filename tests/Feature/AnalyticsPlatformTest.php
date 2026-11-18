<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\PrototypeCase;
use App\Models\User;
use App\Models\VisitorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_prototype_is_open_and_does_not_request_personal_data(): void
    {
        $this->get('/')->assertOk()->assertSee('Tu casa también tiene futuro')->assertSee('const analyticsEnabled=true')->assertDontSee('name="dni"');
        $this->post('/registro-guia')->assertNotFound();
    }

    public function test_anonymous_event_is_stored_once_without_personal_data(): void
    {
        PrototypeCase::create(['slug' => 'posesion', 'title' => 'Caso de prueba', 'summary' => 'Resumen', 'sort_order' => 1]);
        $payload = [
            'event_uuid' => '8f08f0fc-27d6-4677-aeaa-89ac2ef0af19',
            'session_key' => '33fd59f5-92d0-45a2-a38b-a59c012f26c6',
            'event_type' => 'case_view',
            'case' => 'posesion',
            'page' => '/#casos',
            'duration_seconds' => 12,
        ];

        $this->postJson('/analytics/events', $payload)->assertCreated();
        $this->postJson('/analytics/events', $payload)->assertOk();

        $this->assertSame(1, VisitorSession::count());
        $this->assertSame(1, AnalyticsEvent::count());
        $this->assertDatabaseMissing('analytics_events', ['page' => '12345678']);
    }

    public function test_dashboard_is_private_and_requires_an_admin_account(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin')->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Resumen de uso del prototipo');
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('const analyticsEnabled=false');
        $this->actingAs($admin)->getJson('/admin/analytics')->assertOk()->assertJsonPath('has_data', false);
    }

    public function test_admin_can_export_real_event_rows_as_csv(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $session = VisitorSession::create([
            'session_key' => '33fd59f5-92d0-45a2-a38b-a59c012f26c6', 'device' => 'desktop', 'browser' => 'Chrome',
            'landing_page' => '/', 'started_at' => now(), 'last_seen_at' => now(),
        ]);
        AnalyticsEvent::create([
            'event_uuid' => '8f08f0fc-27d6-4677-aeaa-89ac2ef0af19', 'visitor_session_id' => $session->id,
            'event_type' => 'page_view', 'page' => '/', 'created_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/export.csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($admin)->get('/admin/export/summary.csv')->assertOk();
        $this->actingAs($admin)->get('/admin/export/cases.csv')->assertOk();
    }
}
