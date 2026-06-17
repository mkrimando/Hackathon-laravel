<?php

namespace Tests\Feature;

use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\SeedsScheduling;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    
    use RefreshDatabase;
    use SeedsScheduling;
    
    public function test_calendar_returns_services_with_configuration_and_slots(): void 
    {
        $this->seedScheduling();

        $response = $this->getJson('/api/calendar');

        $response->assertOk()
            ->assertJsonStructure([
                'services' => [
                    '*' => [
                        'id',
                        'name',
                        'duration_minutes',
                        'slot_interval_minutes',
                        'break_between_minutes',
                        'max_clients_per_slot',
                        'max_booking_days',
                        'booking_window',
                        'opening_hours',
                        'breaks',
                        'closures',
                        'days',
                    ],
                ],
            ]);

        $services = $response->json('services');
        $this->assertCount(2, $services);

        $mens = collect($services)->firstWhere('name', "Men's Haircut");
        $this->assertSame(30, $mens['duration_minutes']);
        $this->assertSame(10, $mens['slot_interval_minutes']);
        $this->assertSame(5, $mens['break_between_minutes']);
        $this->assertSame(3, $mens['max_clients_per_slot']);

        $monday = collect($mens['days'])->firstWhere('date', '2026-06-16');
        $this->assertNotNull($monday);
        $this->assertNotEmpty($monday['slots']);

        $slotStarts = collect($monday['slots'])->pluck('start')->map(
            fn (string $start) => Carbon::parse($start)->format('H:i')
        );

        $this->assertTrue($slotStarts->contains('08:00'));
        $this->assertFalse($slotStarts->contains('08:02'));
        $this->assertFalse($slotStarts->contains('07:00'));
        $this->assertFalse($slotStarts->contains('12:15'));
    }

    public function test_calendar_can_filter_by_service_and_date(): void
    {
        $this->seedScheduling();

        $response = $this->getJson('/api/calendar?service_id=1&date=2026-06-16');

        $response->assertOk();
        $services = $response->json('services');
        $this->assertCount(1, $services);
        $this->assertCount(1, $services[0]['days']);
        $this->assertSame('2026-06-16', $services[0]['days'][0]['date']);
    }
}
