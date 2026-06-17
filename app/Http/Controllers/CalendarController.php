<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        private SlotAvailabilityService $slotAvailabilityService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $servicesQuery = Service::query()
            ->with(['openingHours', 'breaks', 'closures', 'bookings.attendees']);

        if (isset($validated['service_id'])) {
            $servicesQuery->where('id', $validated['service_id']);
        }

        $services = $servicesQuery->get();
        $today = Carbon::today();

        $payload = $services->map(function (Service $service) use ($validated, $today) {
            $bookingWindowEnd = $this->slotAvailabilityService->bookingWindowEnd($service, $today);

            if (isset($validated['date'])) {
                $fromDate = Carbon::parse($validated['date'])->startOfDay();
                $toDate = $fromDate->copy();
            } else {
                $fromDate = $today->copy();
                $toDate = $bookingWindowEnd->copy();
            }

            if ($fromDate->lt($today)) {
                $days = [];
            } elseif ($toDate->gt($bookingWindowEnd)) {
                $effectiveFrom = $fromDate->copy();
                $days = $this->slotAvailabilityService->getCalendarData(
                    $service,
                    $effectiveFrom,
                    $bookingWindowEnd->copy()
                );
            } else {
                $days = $this->slotAvailabilityService->getCalendarData($service, $fromDate, $toDate);
            }

            return [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'slot_interval_minutes' => $service->slot_interval_minutes,
                'break_between_minutes' => $service->break_between_minutes,
                'max_clients_per_slot' => $service->max_clients_per_slot,
                'max_booking_days' => $service->max_booking_days,
                'booking_window' => [
                    'from' => $today->toDateString(),
                    'to' => $bookingWindowEnd->toDateString(),
                ],
                'opening_hours' => $service->openingHours->map(fn ($hour) => [
                    'day_of_week' => $hour->day_of_week,
                    'open_time' => substr((string) $hour->open_time, 0, 5),
                    'close_time' => substr((string) $hour->close_time, 0, 5),
                    'is_closed' => $hour->is_closed,
                ])->values(),
                'breaks' => $service->breaks->map(fn ($break) => [
                    'name' => $break->name,
                    'start_time' => substr((string) $break->start_time, 0, 5),
                    'end_time' => substr((string) $break->end_time, 0, 5),
                ])->values(),
                'closures' => $service->closures->map(fn ($closure) => [
                    'reason' => $closure->reason,
                    'start_datetime' => Carbon::parse($closure->start_datetime)->toIso8601String(),
                    'end_datetime' => Carbon::parse($closure->end_datetime)->toIso8601String(),
                ])->values(),
                'days' => $days,
            ];
        });

        return response()->json([
            'services' => $payload->values(),
        ]);
    }
}
