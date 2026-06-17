<?php

namespace App\Services;

use App\Exceptions\InvalidSlotException;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SlotAvailabilityService
{
    public function getCalendarData(
        Service $service,
        CarbonInterface $fromDate,
        CarbonInterface $toDate
    ): array {
        $days = [];
        $current = $fromDate->copy()->startOfDay();
        $end = $toDate->copy()->startOfDay();

        while ($current->lte($end)) {
            $slots = $this->getAvailableSlotsForDate($service, $current);
            if ($slots !== []) {
                $days[] = [
                    'date' => $current->toDateString(),
                    'slots' => $slots,
                ];
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * @return array<int, array{start: string, available_spots: int, max_clients: int}>
     */
    public function getAvailableSlotsForDate(Service $service, CarbonInterface $date): array
    {
        if (! $this->isWithinBookingWindow($service, $date)) {
            return [];
        }

        $openingHour = $service->openingHours
            ->firstWhere('day_of_week', $date->dayOfWeek);

        if ($openingHour === null || $openingHour->is_closed) {
            return [];
        }

        if ($this->isFullyClosedByClosure($service, $date)) {
            return [];
        }

        $openTime = $this->parseTimeOnDate($openingHour->open_time, $date);
        $closeTime = $this->parseTimeOnDate($openingHour->close_time, $date);
        $slotStarts = $this->generateSlotStarts($service, $openTime, $closeTime);
        $bookingCounts = $this->bookingCountsForDate($service, $date);

        $slots = [];

        foreach ($slotStarts as $slotStart) {
            if ($this->slotOverlapsBreak($service, $slotStart)) {
                continue;
            }

            if ($this->slotOverlapsClosure($service, $slotStart)) {
                continue;
            }

            if ($this->slotOverlapsBreakBetweenAppointments($service, $slotStart, $bookingCounts)) {
                continue;
            }

            $booked = $this->bookedCountForSlot($service, $slotStart, $bookingCounts);
            $available = $service->max_clients_per_slot - $booked;

            if ($available <= 0) {
                continue;
            }

            $slots[] = [
                'start' => $slotStart->toIso8601String(),
                'available_spots' => $available,
                'max_clients' => $service->max_clients_per_slot,
            ];
        }

        return $slots;
    }

    public function assertSlotBookable(Service $service, CarbonInterface $slotStart, int $attendeeCount): void
    {
        if ($attendeeCount < 1) {
            throw new InvalidSlotException('At least one attendee is required.');
        }

        $slotStart = Carbon::parse($slotStart)->seconds(0);

        if ($slotStart->format('Y-m-d H:i:s') !== Carbon::parse($slotStart)->seconds(0)->format('Y-m-d H:i:s')) {
            throw new InvalidSlotException('The requested slot time is invalid.');
        }

        if (! $this->isWithinBookingWindow($service, $slotStart)) {
            throw new InvalidSlotException('The requested slot is outside the allowed booking window.');
        }

        $openingHour = $service->openingHours
            ->firstWhere('day_of_week', $slotStart->dayOfWeek);

        if ($openingHour === null || $openingHour->is_closed) {
            throw new InvalidSlotException('The requested slot falls on a day when the service is closed.');
        }

        $openTime = $this->parseTimeOnDate($openingHour->open_time, $slotStart);
        $closeTime = $this->parseTimeOnDate($openingHour->close_time, $slotStart);

        if ($slotStart->lt($openTime)) {
            throw new InvalidSlotException('The requested slot is before opening hours.');
        }

        $slotEnd = $slotStart->copy()->addMinutes($service->duration_minutes);

        if ($slotEnd->gt($closeTime)) {
            throw new InvalidSlotException('The requested slot extends beyond closing hours.');
        }

        if (! $this->isAlignedToSlotGrid($service, $openTime, $slotStart)) {
            throw new InvalidSlotException('The requested slot does not align with the bookable schedule.');
        }

        if ($this->slotOverlapsBreak($service, $slotStart)) {
            throw new InvalidSlotException('The requested slot falls within a configured break.');
        }

        if ($this->slotOverlapsClosure($service, $slotStart)) {
            throw new InvalidSlotException('The requested slot falls within a planned closure.');
        }

        $bookingCounts = $this->bookingCountsForDate($service, $slotStart);

        if ($this->slotOverlapsBreakBetweenAppointments($service, $slotStart, $bookingCounts)) {
            throw new InvalidSlotException('The requested slot falls within a break between appointments.');
        }

        $booked = $this->bookedCountForSlot($service, $slotStart, $bookingCounts);

        if ($booked + $attendeeCount > $service->max_clients_per_slot) {
            throw new InvalidSlotException('The requested slot is fully booked.');
        }
    }

    public function bookingWindowEnd(Service $service, ?CarbonInterface $reference = null): Carbon
    {
        $reference = $reference ? Carbon::parse($reference)->startOfDay() : Carbon::today();

        return $reference->copy()->addDays($service->max_booking_days);
    }

    private function isWithinBookingWindow(Service $service, CarbonInterface $date): bool
    {
        $day = Carbon::parse($date)->startOfDay();
        $today = Carbon::today();

        if ($day->lt($today)) {
            return false;
        }

        return $day->lte($this->bookingWindowEnd($service, $today));
    }

    private function isFullyClosedByClosure(Service $service, CarbonInterface $date): bool
    {
        $dayStart = Carbon::parse($date)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();

        foreach ($service->closures as $closure) {
            $closureStart = Carbon::parse($closure->start_datetime);
            $closureEnd = Carbon::parse($closure->end_datetime);

            if ($closureStart->lte($dayStart) && $closureEnd->gte($dayEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function generateSlotStarts(Service $service, Carbon $openTime, Carbon $closeTime): Collection
    {
        $slots = collect();
        $current = $openTime->copy();

        while ($current->copy()->addMinutes($service->duration_minutes)->lte($closeTime)) {
            $slots->push($current->copy());
            $current->addMinutes($service->slot_interval_minutes);
        }

        return $slots;
    }

    private function isAlignedToSlotGrid(Service $service, Carbon $openTime, Carbon $slotStart): bool
    {
        $openMinutes = ($openTime->hour * 60) + $openTime->minute;
        $slotMinutes = ($slotStart->hour * 60) + $slotStart->minute;
        $offset = $slotMinutes - $openMinutes;

        if ($offset < 0) {
            return false;
        }

        return $offset % $service->slot_interval_minutes === 0;
    }

    private function slotOverlapsBreak(Service $service, CarbonInterface $slotStart): bool
    {
        $slotEnd = Carbon::parse($slotStart)->addMinutes($service->duration_minutes);

        foreach ($service->breaks as $break) {
            $breakStart = $this->parseTimeOnDate($break->start_time, $slotStart);
            $breakEnd = $this->parseTimeOnDate($break->end_time, $slotStart);

            if ($this->periodsOverlap(Carbon::parse($slotStart), $slotEnd, $breakStart, $breakEnd)) {
                return true;
            }
        }

        return false;
    }

    private function slotOverlapsClosure(Service $service, CarbonInterface $slotStart): bool
    {
        $slotEnd = Carbon::parse($slotStart)->addMinutes($service->duration_minutes);

        foreach ($service->closures as $closure) {
            $closureStart = Carbon::parse($closure->start_datetime);
            $closureEnd = Carbon::parse($closure->end_datetime);

            if ($this->periodsOverlap(Carbon::parse($slotStart), $slotEnd, $closureStart, $closureEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $bookingCounts
     */
    private function slotOverlapsBreakBetweenAppointments(
        Service $service,
        CarbonInterface $slotStart,
        array $bookingCounts
    ): bool {
        if ($service->break_between_minutes === 0) {
            return false;
        }

        $slotStartCarbon = Carbon::parse($slotStart);

        foreach ($bookingCounts as $bookedSlotKey => $count) {
            if ($count <= 0) {
                continue;
            }

            $bookedStart = Carbon::createFromFormat('Y-m-d H:i:s', $bookedSlotKey);
            $cleanupStart = $bookedStart->copy()->addMinutes($service->duration_minutes);
            $cleanupEnd = $cleanupStart->copy()->addMinutes($service->break_between_minutes);

            if ($slotStartCarbon->gte($cleanupStart) && $slotStartCarbon->lt($cleanupEnd)) {
                if ($this->hasExtendedBookingCoverageAt($service, $slotStartCarbon, $bookingCounts, $bookedSlotKey)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $bookingCounts
     */
    private function hasExtendedBookingCoverageAt(
        Service $service,
        Carbon $slotStart,
        array $bookingCounts,
        string $excludeBookedSlotKey
    ): bool {
        foreach ($bookingCounts as $bookedSlotKey => $count) {
            if ($count <= 0 || $bookedSlotKey === $excludeBookedSlotKey) {
                continue;
            }

            $bookedStart = Carbon::createFromFormat('Y-m-d H:i:s', $bookedSlotKey);
            $bookedEnd = $bookedStart->copy()->addMinutes(
                $service->duration_minutes + $service->break_between_minutes
            );

            if ($slotStart->gte($bookedStart) && $slotStart->lt($bookedEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count attendees whose appointment overlaps this slot's duration.
     *
     * @param  array<string, int>  $bookingCounts
     */
    private function bookedCountForSlot(
        Service $service,
        CarbonInterface $slotStart,
        array $bookingCounts
    ): int {
        $slotStartCarbon = Carbon::parse($slotStart);
        $slotEnd = $slotStartCarbon->copy()->addMinutes($service->duration_minutes);
        $booked = 0;

        foreach ($bookingCounts as $bookedSlotKey => $count) {
            $bookedStart = Carbon::createFromFormat('Y-m-d H:i:s', $bookedSlotKey);
            $bookedEnd = $bookedStart->copy()->addMinutes(
                $service->duration_minutes + $service->break_between_minutes
            );

            if ($this->periodsOverlap($slotStartCarbon, $slotEnd, $bookedStart, $bookedEnd)) {
                $booked += $count;
            }
        }

        return $booked;
    }

    /**
     * @return array<string, int>
     */
    private function bookingCountsForDate(Service $service, CarbonInterface $date): array
    {
        $dayStart = Carbon::parse($date)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();

        $counts = [];

        $bookings = $service->bookings()
            ->whereBetween('slot_start', [$dayStart, $dayEnd])
            ->withCount('attendees')
            ->get();

        foreach ($bookings as $booking) {
            $key = Carbon::parse($booking->slot_start)->format('Y-m-d H:i:s');
            $counts[$key] = ($counts[$key] ?? 0) + $booking->attendees_count;
        }

        return $counts;
    }

    private function parseTimeOnDate(string $time, CarbonInterface $date): Carbon
    {
        $timePart = strlen($time) === 5 ? $time.':00' : $time;

        return Carbon::parse($date->toDateString().' '.$timePart);
    }

    private function periodsOverlap(
        Carbon $startA,
        Carbon $endA,
        Carbon $startB,
        Carbon $endB
    ): bool {
        return $startA->lt($endB) && $endA->gt($startB);
    }
}
