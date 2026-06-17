<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsScheduling;
use Tests\TestCase;

class SlotAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsScheduling;

    private SlotAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SlotAvailabilityService::class);
    }

    public function test_mens_slots_respect_break_between_after_booking(): void
    {
        $this->seedScheduling();

        $mens = Service::query()->with(['openingHours', 'breaks', 'closures', 'bookings.attendees'])
            ->where('name', "Men's Haircut")
            ->firstOrFail();

        $date = Carbon::parse('2026-06-16');

        $booking = $mens->bookings()->create(['slot_start' => $date->copy()->setTime(8, 0)]);
        $booking->attendees()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $mens->load(['bookings.attendees']);

        $slots = collect($this->service->getAvailableSlotsForDate($mens, $date))
            ->pluck('start')
            ->map(fn (string $start) => Carbon::parse($start)->format('H:i'));

        $this->assertFalse($slots->contains('08:30'));
        $this->assertTrue($slots->contains('08:40'));
    }

    public function test_booking_window_end_is_seven_days_from_today(): void
    {
        $this->seedScheduling();

        $mens = Service::query()->firstOrFail();
        $end = $this->service->bookingWindowEnd($mens);

        $this->assertSame('2026-06-23', $end->toDateString());
    }
}
