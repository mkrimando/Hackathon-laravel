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
     * User story: open the scheduling page and see all available slots for the day.
     *
     * This test validates the calendar API returns service configuration, opening
     * hours, breaks, closures, and available slots for each service.
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
     * Business story: allow users to view available slots for a specific service
     * on a chosen date.
     *
     * This test checks that the calendar API can filter by service and date.
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
     * Business story: exclude non-bookable dates from the calendar view.
     *
     * This test verifies that Sunday and the seeded public holiday do not
     * appear as available days in the calendar.
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

    /**
     * Verify the calendar returns only valid slots for a specific date.
     *
     * User story: select a date and see all available slots for that day.
     */
    public function test_calendar_returns_available_slots_for_specific_date(): void
    {
        $this->seedScheduling();

        $serviceId = Service::query()->where('name', "Men's Haircut")->firstOrFail()->id;
        $response = $this->getJson("/api/calendar?service_id={$serviceId}&date=2026-06-16");

        $response->assertOk();
        $services = $response->json('services');
        $this->assertCount(1, $services);

        $slots = collect($services[0]['days'][0]['slots'])->pluck('start')->map(
            fn (string $start) => Carbon::parse($start)->format('H:i')
        );

        $this->assertTrue($slots->contains('08:00'));
        $this->assertFalse($slots->contains('07:00'));
        $this->assertFalse($slots->contains('12:15'));
    }
}
