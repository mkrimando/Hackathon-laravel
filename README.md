# Backend Hackathon: Time Scheduling REST API

A Laravel backend API for service scheduling, availability, and appointment booking.

This repository implements the hackathon requirements using a SQL database, Eloquent ORM, and Laravel.

## Project Summary

The API supports:
- service-specific bookable schedules
- daily opening hours and service breaks
- planned closures and holiday blocks
- appointment duration, slot interval, and cleanup time
- configurable maximum clients per slot
- multiple attendees in one booking
- validation of invalid and unavailable slots
- calendar payload for SPA consumption

## Tech Stack

- PHP 8.x
- Laravel 10
- Eloquent ORM
- MySQL-compatible SQL database
- PHPUnit for automated tests

## Requirements Covered

- SQL database with related tables and foreign keys
- REST API only (no frontend)
- GET endpoint for calendar data and slot availability
- POST endpoint for booking one or more people for a single slot
- validation of booking schema and slot rules
- support for multiple services with independent schedules
- different opening hours per weekday
- configurable bookable window length
- support for break windows and planned closures
- support for multiple clients per slot
- attendee details per person
- no uniqueness restriction on attendee details across bookings

## API Endpoints

### GET /api/calendar

Returns all calendar data needed to display service availability.

Query parameters:
- `service_id` (optional)
- `date` (optional, `YYYY-MM-DD`)

Response includes:
- service configuration
- booking window
- opening hours
- breaks
- closures
- available slots for the requested date or date range

### POST /api/bookings

Creates a booking for a single slot and one or more attendees.

Request body:
- `service_id` (integer, required)
- `slot_start` (datetime string, required)
- `attendees` (array, required)
  - `first_name` (string)
  - `last_name` (string)
  - `email` (email string)

Validation rules include:
- service exists
- slot lies within opening hours
- slot aligns with the configured interval
- slot does not fall in a break or closure
- slot has available capacity
- attendee details are provided for each person
- attendee names must be unique within the booking

## Database Schema

The project uses normalized tables with foreign keys.

Main models and tables:
- `services`
- `service_opening_hours`
- `service_breaks`
- `service_closures`
- `bookings`
- `booking_attendees`

Relationships:
- `Service` has many `ServiceOpeningHour`
- `Service` has many `ServiceBreak`
- `Service` has many `ServiceClosure`
- `Service` has many `Booking`
- `Booking` has many `BookingAttendee`

## Application Architecture

- `routes/api.php` — public API routes
- `app/Http/Controllers/CalendarController.php` — calendar endpoint
- `app/Http/Controllers/BookingController.php` — booking creation
- `app/Services/SlotAvailabilityService.php` — slot generation and validation logic
- `app/Models/` — service and booking models
- `database/seeders/SchedulingSeeder.php` — seeded schedule data
- `tests/Feature/` — API feature tests
- `tests/Unit/` — business logic tests

## Seed Data Configuration

The seeded schedule matches the hackathon requirements:

### Men's Haircut
- Sunday off
- Monday–Friday: 08:00–20:00
- Saturday: 10:00–22:00
- Lunch break: 12:00–13:00
- Cleaning break: 15:00–16:00
- Slot interval: 10 minutes
- Cleanup between bookings: 5 minutes
- Max clients per slot: 3
- Public holiday on the third day from now

### Women's Haircut
- Sunday off
- Monday–Friday: 08:00–20:00
- Saturday: 10:00–22:00
- Lunch break: 12:00–13:00
- Cleaning break: 15:00–16:00
- Slot interval: 60 minutes
- Cleanup between bookings: 10 minutes
- Max clients per slot: 3
- Public holiday on the third day from now

## Booking Validation Examples

The API rejects:
- booking at `07:00` before opening hours
- booking at `08:02` because it does not align with the slot interval
- booking at `12:15` during a lunch break
- booking during a planned closure
- booking when the slot is fully booked

## How to Run Locally

1. Install PHP dependencies:
   ```bash
   composer install
   ```
2. Install frontend packages if needed:
   ```bash
   npm install
   ```
3. Copy environment settings:
   ```bash
   cp .env.example .env
   ```
4. Configure database credentials in `.env`
5. Run migrations and seed the database:
   ```bash
   php artisan migrate --seed
   ```
6. Start the development server:
   ```bash
   php artisan serve
   ```

## Automated Tests

Run the full test suite with:

```bash
php artisan test
```

This ensures booking and calendar behavior is covered and edge cases are validated.

## Notes

- This project is backend-only.
- The API is designed to be consumed by an SPA or mobile frontend.
- All schedule rules and capacity logic run on the backend, not in the client.

## License

MIT
