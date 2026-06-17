<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsScheduling;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsScheduling;

    /**
     * Ensure a user can successfully create a single-person booking.
     *
     * This test seeds the scheduling data, selects a seeded service, and
     * posts a booking request for one attendee. It verifies the response
     * returns HTTP 201 and the booking is persisted with the correct attendee.
     */
    public function test_user_can_book_an_appointment_for_one_person(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                [
                    'first_name' => 'Mark',
                    'last_name' => 'Rimando',
                    'email' => 'mark@example.com',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('booking.service_id', $serviceId)
            ->assertJsonPath('booking.attendees.0.email', 'mark@example.com');

        $this->assertDatabaseHas('bookings', [
            'service_id' => $serviceId,
        ]);
        $this->assertDatabaseHas('booking_attendees', [
            'first_name' => 'Mark',
            'email' => 'mark@example.com',
        ]);
    }
}
