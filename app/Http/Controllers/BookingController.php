<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidSlotException;
use App\Models\Booking;
use App\Models\Service;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{

    public function __construct(
        private SlotAvailabilityService $slotAvailabilityService
    ) {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'slot_start' => ['required', 'date'],
            'attendees' => ['required', 'array', 'min:1'],
            'attendees.*.first_name' => ['required', 'string', 'max:255'],
            'attendees.*.last_name' => ['required', 'string', 'max:255'],
            'attendees.*.email' => ['required', 'email', 'max:255'],
        ]);

        // Validate that attendees have unique names; the same email may be reused
        $attendeeNameKeys = collect($validated['attendees'])
            ->map(fn ($attendee) => mb_strtolower(trim($attendee['first_name'])) . '|' . mb_strtolower(trim($attendee['last_name'])))
            ->toArray();

        if (count($attendeeNameKeys) !== count(array_unique($attendeeNameKeys))) {
            throw ValidationException::withMessages([
                'attendees' => ['Each attendee must have a unique first and last name combination.'],
            ]);
        }

        $service = Service::query()
            ->with(['openingHours', 'breaks', 'closures', 'bookings.attendees'])
            ->findOrFail($validated['service_id']);

        $slotStart = Carbon::parse($validated['slot_start'])->seconds(0);
        $attendeeCount = count($validated['attendees']);

        try {
            $this->slotAvailabilityService->assertSlotBookable($service, $slotStart, $attendeeCount);
        } catch (InvalidSlotException $exception) {
            throw ValidationException::withMessages([
                'slot_start' => [$exception->getMessage()],
            ]);
        }

        $booking = DB::transaction(function () use ($service, $slotStart, $validated) {
            $service->refresh();
            $service->load(['openingHours', 'breaks', 'closures', 'bookings.attendees']);

            $this->slotAvailabilityService->assertSlotBookable(
                $service,
                $slotStart,
                count($validated['attendees'])
            );

            $booking = Booking::create([
                'service_id' => $service->id,
                'slot_start' => $slotStart,
            ]);

            foreach ($validated['attendees'] as $attendee) {
                $booking->attendees()->create($attendee);
            }

            return $booking->load('attendees');
        });

        return response()->json([
            'message' => 'Booking created successfully.',
            'booking' => [
                'id' => $booking->id,
                'service_id' => $booking->service_id,
                'slot_start' => Carbon::parse($booking->slot_start)->toIso8601String(),
                'attendees' => $booking->attendees->map(fn ($attendee) => [
                    'first_name' => $attendee->first_name,
                    'last_name' => $attendee->last_name,
                    'email' => $attendee->email,
                ])->values(),
            ],
        ], 201);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function show(Booking $booking)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function edit(Booking $booking)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Booking $booking)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function destroy(Booking $booking)
    {
        //
    }
}
