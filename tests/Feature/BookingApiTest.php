<?php

namespace Tests\Feature;

use App\Models\Service;
use Carbon\Carbon;
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


    /** Test that booking during cleaning break is rejected */
    public function test_booking_during_cleaning_break_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T15:30:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }


    /** Test that booking on a public holiday is rejected */
    public function test_booking_on_public_holiday_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-18T08:00:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }


    /** Test that booking on a Sunday is rejected */
    public function test_booking_on_sunday_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-21T10:00:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }



    /** Test that booking beyond the maximum advance booking period is rejected */
    public function test_booking_beyond_max_days_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-24T08:00:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }


    /** Test that a fully booked time slot cannot accept additional bookings */
    public function test_fully_booked_slot_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $payload = [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                ['first_name' => 'A', 'last_name' => 'One', 'email' => 'a@example.com'],
                ['first_name' => 'B', 'last_name' => 'Two', 'email' => 'b@example.com'],
                ['first_name' => 'C', 'last_name' => 'Three', 'email' => 'c@example.com'],
            ],
        ];

        $this->postJson('/api/bookings', $payload)->assertCreated();

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                ['first_name' => 'D', 'last_name' => 'Four', 'email' => 'd@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }

    /** Test that invalid attendee data (e.g., invalid email) is rejected */
    public function test_invalid_attendee_payload_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'not-an-email'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendees.0.email']);
    }

    /** Test that specific services (women's haircut) use hourly time slots */
    public function test_womens_haircut_uses_hourly_slots(): void
    {
        $this->seedScheduling();

        $serviceId = Service::query()->where('name', "Women's Haircut")->firstOrFail()->id;
        $response = $this->getJson('/api/calendar?service_id=' . $serviceId . '&date=2026-06-16');
        $slots = collect($response->json('services.0.days.0.slots'))->pluck('start')->map(
            fn (string $start) => Carbon::parse($start)->format('H:i')
        );

        $this->assertTrue($slots->contains('08:00'));
        $this->assertTrue($slots->contains('09:00'));
        $this->assertFalse($slots->contains('08:10'));
    }

    /** Test that different services have independent booking availability */
    public function test_services_are_independent(): void
    {
        $this->seedScheduling();

        $serviceId1 = Service::query()->where('name', "Men's Haircut")->firstOrFail()->id;
        $serviceId2 = Service::query()->where('name', "Women's Haircut")->firstOrFail()->id;

        $this->postJson('/api/bookings', [
            'service_id' => $serviceId1,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                ['first_name' => 'A', 'last_name' => 'One', 'email' => 'a@example.com'],
                ['first_name' => 'B', 'last_name' => 'Two', 'email' => 'b@example.com'],
                ['first_name' => 'C', 'last_name' => 'Three', 'email' => 'c@example.com'],
            ],
        ])->assertCreated();

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId2,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                ['first_name' => 'D', 'last_name' => 'Four', 'email' => 'd@example.com'],
            ],
        ]);

        $response->assertCreated();
    }


}
