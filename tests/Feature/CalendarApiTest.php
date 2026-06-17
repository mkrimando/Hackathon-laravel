<?php

namespace Tests\Feature;

use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsScheduling;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    
    use RefreshDatabase;
    use SeedsScheduling;
    
    /**
     * Verify the calendar payload contains both services with full schedule data.
     *
     * This test ensures the API response includes service configuration fields,
     * opening hours, breaks, closures, and computed daily slots for the seeded schedule.
     */
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

    /**
     * Confirm the calendar can filter by a single service and a specific date.
     *
     * The test validates service filtering returns one service and only one day
     * for the requested date in the seeded schedule.
     */
    public function test_calendar_can_filter_by_service_and_date(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;
        $response = $this->getJson("/api/calendar?service_id={$serviceId}&date=2026-06-16");

        $response->assertOk();
        $services = $response->json('services');
        $this->assertCount(1, $services);
        $this->assertCount(1, $services[0]['days']);
        $this->assertSame('2026-06-16', $services[0]['days'][0]['date']);
    }
    
    /**
     * Ensure days with no availability are excluded from the calendar.
     *
     * This test covers both Sunday closure and a seeded public holiday,
     * verifying those dates are omitted from the returned service days.
     */
    public function test_calendar_excludes_sunday_and_public_holiday(): void
    {
        $this->seedScheduling();

        $response = $this->getJson('/api/calendar');
        $mens = collect($response->json('services'))->firstWhere('name', "Men's Haircut");
        $dates = collect($mens['days'])->pluck('date');

        $this->assertFalse($dates->contains('2026-06-21'));
        $this->assertFalse($dates->contains('2026-06-18'));
    }

    /**
     * Validate Saturday uses the later opening hours defined in the schedule.
     *
     * This confirms the calendar returns 10:00 slots on Saturday and does not
     * include weekday opening hours like 08:00.
     */
    public function test_saturday_uses_later_opening_hours(): void
    {
        $this->seedScheduling();

        $response = $this->getJson('/api/calendar?date=2026-06-20');
        $mens = collect($response->json('services'))->firstWhere('name', "Men's Haircut");
        $slots = collect($mens['days'][0]['slots'])->pluck('start')->map(
            fn (string $start) => Carbon::parse($start)->format('H:i')
        );

        $this->assertTrue($slots->contains('10:00'));
        $this->assertFalse($slots->contains('08:00'));
    }
}
