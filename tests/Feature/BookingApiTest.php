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

    /**
     * Verify a single booking can accommodate multiple attendees in one request.
     *
     * This test creates a booking for three attendees simultaneously and confirms
     * the API persists all attendees correctly within a single booking record.
     */
    public function test_user_can_book_multiple_people_in_one_request(): void
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
                [
                    'first_name' => 'Thet',
                    'last_name' => 'Aung',
                    'email' => 'thet@example.com',
                ],
                [
                    'first_name' => 'Ans',
                    'last_name' => 'Jabar',
                    'email' => 'ans@example.com',
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('booking_attendees', 3);
    }


    /**
     * Confirm that the system allows unique names even when email addresses differ.
     *
     * This test verifies that multiple attendees with distinct names can book
     * under the same service and time slot, even if their email addresses are different.
     */
    public function test_duplicate_person_details_are_allowed(): void
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
                [
                    'first_name' => 'John',
                    'last_name' => 'Rimando',
                    'email' => 'mark@example.com',
                ],
            ],
        ]);

        $response->assertCreated();
    }

    /**
     * Verify that the system allows unique person details when names differ.
     *
     * This test ensures that attendees with different names can book successfully,
     * even if their email addresses are not the same.
     */
    public function test_unique_person_details_are_allowed(): void
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
                [
                    'first_name' => 'John',
                    'last_name' => 'Rimando',
                    'email' => 'mark1@example.com',
                ],
            ],
        ]);

        $response->assertCreated();
    }

    /**
     * Verify that multiple attendees cannot have the same first and last name.
     *
     * This test ensures the system rejects bookings where two or more attendees
     * have identical first and last names, preventing duplicate person entries
     * within a single booking.
     */
    public function test_duplicate_names_are_not_allowed(): void
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
                [
                    'first_name' => 'Mark',
                    'last_name' => 'Rimando',
                    'email' => 'mark@example.com',
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }

    /** Test that booking before service opening hours is rejected */
    public function test_booking_before_opening_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;
        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T07:00:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }


    /** Test that booking at a time not aligned with service slots is rejected */
    public function test_booking_at_unaligned_time_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:02:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }

    /** Test that booking during lunch break is rejected */
    public function test_booking_during_lunch_break_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T12:15:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }

}
