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
     * User story: book an appointment for a single person.
     *
     * This test verifies the API accepts a valid booking request for one
     * attendee, persists the booking, and returns a created response.
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
     * User story: book an appointment for multiple people at once.
     *
     * This test ensures a single booking can include several attendees and
     * that all attendee details are saved correctly.
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
     * Business story: allow a person to book multiple appointments without
     * uniqueness restrictions on email, while still requiring distinct attendee
     * names for separate people.
     *
     * This test covers a parent booking two children using the same contact email.
     */
    public function test_same_email_different_children_names_is_allowed(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                [
                    'first_name' => 'Liam',
                    'last_name' => 'Rimando',
                    'email' => 'parent@example.com',
                ],
                [
                    'first_name' => 'Emma',
                    'last_name' => 'Rimando',
                    'email' => 'parent@example.com',
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


    /**
     * Business story: reject bookings that do not fit into defined slot intervals.
     *
     * This test ensures a request for 08:02 is invalid because it does not align
     * with the service slot grid.
     */
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

    /** Test that booking during coffee break is rejected */
    public function test_booking_during_coffee_break_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::first()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T17:15:00',
            'attendees' => [
                ['first_name' => 'Mark', 'last_name' => 'Rimando', 'email' => 'mark@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);
    }

    /**
     * Test that booking is rejected when it falls within the cleanup break after a prior appointment.
     *
     * Business story: a configured break between appointments must make the next slot invalid.
     */
    public function test_booking_during_break_between_appointments_is_rejected(): void
    {
        $this->seedScheduling();

        $serviceId = Service::query()->where('name', "Men's Haircut")->firstOrFail()->id;

        $firstBooking = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
            ],
        ]);

        $firstBooking->assertCreated();

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'slot_start' => '2026-06-16T08:30:00',
            'attendees' => [
                ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com'],
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


    /**
     * Business story: support planned off-date closures that are service specific.
     *
     * This test verifies that Women's Haircut is closed on the leave date while
     * Men's Haircut remains bookable for the same slot.
     */
    public function test_service_specific_closure_blocks_only_targeted_service(): void
    {
        $this->seedScheduling();

        $womenServiceId = Service::query()->where('name', "Women's Haircut")->firstOrFail()->id;
        $mensServiceId = Service::query()->where('name', "Men's Haircut")->firstOrFail()->id;

        $response = $this->postJson('/api/bookings', [
            'service_id' => $womenServiceId,
            'slot_start' => '2026-06-20T10:00:00',
            'attendees' => [
                ['first_name' => 'Sarah', 'last_name' => 'Lee', 'email' => 'sarah@example.com'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot_start']);

        $bookingResponse = $this->postJson('/api/bookings', [
            'service_id' => $mensServiceId,
            'slot_start' => '2026-06-20T10:00:00',
            'attendees' => [
                ['first_name' => 'Mike', 'last_name' => 'Lee', 'email' => 'mike@example.com'],
            ],
        ]);

        $bookingResponse->assertCreated();
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


    /**
     * Business story: reject slots that are booked out.
     *
     * This test verifies that when the maximum number of clients has already
     * been booked for a slot, further booking attempts fail.
     */
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

    /**
     * User story: validate personal details for each attendee.
     *
     * This test ensures the API rejects invalid attendee data such as a malformed email.
     */
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

    /**
     * Business story: staggered bookings within one appointment duration share capacity.
     *
     * When an earlier slot is partially booked and a later overlapping slot is also
     * booked, the calendar must hide the earlier slot once combined capacity is full.
     */
    public function test_overlapping_slot_bookings_reduce_earlier_slot_availability(): void
    {
        $this->seedScheduling();

        $serviceId1 = Service::query()->where('name', "Men's Haircut")->firstOrFail()->id;

        $this->postJson('/api/bookings', [
            'service_id' => $serviceId1,
            'slot_start' => '2026-06-16T08:00:00',
            'attendees' => [
                ['first_name' => 'A', 'last_name' => 'One', 'email' => 'a@example.com'],
            ],
        ])->assertCreated();

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId1,
            'slot_start' => '2026-06-16T08:10:00',
            'attendees' => [
                ['first_name' => 'B', 'last_name' => 'Two', 'email' => 'b@example.com'],
            ],
        ]);

        $response->assertCreated();

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId1,
            'slot_start' => '2026-06-16T08:20:00',
            'attendees' => [
                ['first_name' => 'C', 'last_name' => 'Three', 'email' => 'c@example.com'],
            ],
        ]);

        $response->assertCreated();

        $response = $this->getJson("/api/calendar?service_id={$serviceId1}&date=2026-06-16");
        $slots = collect($response->json('services.0.days.0.slots'))->pluck('start')->map(
            fn (string $start) => Carbon::parse($start)->format('H:i')
        );

        $this->assertFalse($slots->contains('08:00'));
        $this->assertFalse($slots->contains('08:10'));
        $this->assertFalse($slots->contains('08:20'));
        $this->assertFalse($slots->contains('08:30'));
        $this->assertTrue($slots->contains('08:40'));

        $response = $this->postJson('/api/bookings', [
            'service_id' => $serviceId1,
            'slot_start' => '2026-06-16T08:40:00',
            'attendees' => [
                ['first_name' => 'D', 'last_name' => 'Four', 'email' => 'd@example.com'],
            ],
        ]);

        $response->assertCreated();

        $response = $this->getJson("/api/calendar?service_id={$serviceId1}&date=2026-06-16");
        $slots = collect($response->json('services.0.days.0.slots'))->pluck('start')->map(
            fn (string $start) => Carbon::parse($start)->format('H:i')
        );

        $this->assertFalse($slots->contains('08:40'));
        $this->assertTrue($slots->contains('08:50'));
    }


}
