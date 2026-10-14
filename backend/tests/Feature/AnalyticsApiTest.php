<?php

namespace Tests\Feature;

use App\Services\Analytics\ServerEventTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_events_are_validated_and_deduplicated(): void
    {
        $event = [
            'event_id' => 'event-1',
            'event_name' => 'page_view',
            'visitor_id' => 'visitor-1',
            'session_id' => 'session-1',
            'page_path' => '/',
            'properties' => ['campaign' => 'spring'],
        ];

        $this->postJson('/api/events', ['events' => [$event]])
            ->assertAccepted()
            ->assertExactJson(['accepted' => 1, 'duplicates' => 0]);

        $this->postJson('/api/events', ['events' => [$event]])
            ->assertAccepted()
            ->assertExactJson(['accepted' => 0, 'duplicates' => 1]);

        $this->postJson('/api/events', [
            'events' => ['not-an-event'],
        ])->assertUnprocessable();

        $this->postJson('/api/events', [
            'events' => [[...$event, 'event_id' => 'fake-order', 'event_name' => 'order_created']],
        ])->assertUnprocessable();

        $this->withHeader('X-Analytics-Key', 'wrong')
            ->postJson('/api/events', ['events' => [$event]])
            ->assertUnauthorized();

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseHas('events', ['event_id' => 'event-1', 'source' => 'browser']);
    }

    public function test_event_batch_validation_is_atomic_and_summary_is_zero_filled(): void
    {
        $this->postJson('/api/events', [
            'events' => [
                [
                    'event_id' => 'valid-event',
                    'event_name' => 'page_view',
                    'visitor_id' => 'visitor-1',
                    'session_id' => 'session-1',
                    'page_path' => '/',
                ],
                ['event_name' => 'Invalid name'],
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('events', 0);

        $today = now('UTC')->toDateString();
        $this->getJson("/api/analytics/summary?from=$today&to=$today")
            ->assertOk()
            ->assertJsonPath('totals.total_events', 0)
            ->assertJsonPath('daily.0.day', $today)
            ->assertJsonPath('daily.0.events', 0);

        $this->postJson('/api/events', [
            'events' => [
                [
                    'event_id' => 'page-view',
                    'event_name' => 'page_view',
                    'visitor_id' => 'visitor-1',
                    'session_id' => 'session-1',
                    'page_path' => '/',
                ],
                [
                    'event_id' => 'menu-selection',
                    'event_name' => 'menu_item_selected',
                    'visitor_id' => 'visitor-1',
                    'session_id' => 'session-1',
                ],
            ],
        ])->assertAccepted();

        $this->getJson("/api/analytics/summary?from=$today&to=$today")
            ->assertOk()
            ->assertJsonPath('totals.total_events', 2)
            ->assertJsonPath('totals.page_views', 1)
            ->assertJsonPath('totals.unique_visitors', 1)
            ->assertJsonPath('daily.0.events', 2)
            ->assertJsonPath('popular_pages.0.page_path', '/');

        $this->getJson('/api/analytics/summary?from=2026-03-01&to=2026-01-01')
            ->assertUnprocessable();

        $this->getJson('/api/analytics/summary?from=2026-01-01&to=2026-04-01')
            ->assertUnprocessable();
    }

    public function test_order_uses_server_pricing_and_saves_trusted_event_atomically(): void
    {
        $response = $this->postJson('/api/orders', [
            'item_id' => 'margherita',
            'quantity' => 2,
            'visitor_id' => 'visitor-1',
            'session_id' => 'session-1',
        ])->assertCreated()
            ->assertJsonPath('order.total_cents', 2900)
            ->assertJsonPath('analytics', 'tracked');

        $orderId = $response->json('order.order_id');
        $this->assertDatabaseHas('orders', ['order_id' => $orderId, 'total_cents' => 2900]);
        $this->assertDatabaseHas('events', [
            'event_name' => 'order_created',
            'source' => 'server',
            'visitor_id' => 'visitor-1',
        ]);
        $this->assertSame(
            ['order_id' => $orderId, 'item_id' => 'margherita', 'quantity' => 2, 'total_cents' => 2900],
            json_decode(DB::table('events')->where('event_name', 'order_created')->value('properties'), true, flags: JSON_THROW_ON_ERROR),
        );

        $this->postJson('/api/orders', [
            'item_id' => 'margherita',
            'quantity' => 11,
            'visitor_id' => 'visitor-1',
            'session_id' => 'session-1',
        ])->assertUnprocessable();

        $this->assertSame(1, DB::table('orders')->count());
    }

    public function test_server_tracker_persists_business_events_as_trusted_events(): void
    {
        $eventId = app(ServerEventTracker::class)->track(
            'payment_received',
            'visitor-1',
            'session-1',
            ['amount_cents' => 2900],
        );

        $this->assertDatabaseHas('events', [
            'event_id' => $eventId,
            'event_name' => 'payment_received',
            'visitor_id' => 'visitor-1',
            'session_id' => 'session-1',
            'source' => 'server',
        ]);
    }

    public function test_menu_returns_server_owned_prices(): void
    {
        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'margherita',
                'name' => 'Garden Margherita',
                'price_cents' => 1450,
            ]);
    }
}
