<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceBreak;
use App\Models\ServiceClosure;
use App\Models\ServiceOpeningHour;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SchedulingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMensHaircut();
        $this->seedWomensHaircut();
    }

    private function seedMensHaircut(): void
    {
        $service = Service::create([
            'name' => "Men's Haircut",
            'duration_minutes' => 30,
            'slot_interval_minutes' => 10,
            'break_between_minutes' => 5,
            'max_clients_per_slot' => 3,
            'max_booking_days' => 7,
        ]);

        $this->seedSharedSchedule($service);
    }

    private function seedWomensHaircut(): void
    {
        $service = Service::create([
            'name' => "Women's Haircut",
            'duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'break_between_minutes' => 10,
            'max_clients_per_slot' => 3,
            'max_booking_days' => 7,
        ]);

        $this->seedSharedSchedule($service);
    }

    private function seedSharedSchedule(Service $service): void
    {
        for ($day = 0; $day <= 6; $day++) {
            if ($day === Carbon::SUNDAY) {
                ServiceOpeningHour::create([
                    'service_id' => $service->id,
                    'day_of_week' => $day,
                    'open_time' => null,
                    'close_time' => null,
                    'is_closed' => true,
                ]);

                continue;
            }

            $isSaturday = $day === Carbon::SATURDAY;

            ServiceOpeningHour::create([
                'service_id' => $service->id,
                'day_of_week' => $day,
                'open_time' => $isSaturday ? '10:00:00' : '08:00:00',
                'close_time' => $isSaturday ? '22:00:00' : '20:00:00',
                'is_closed' => false,
            ]);
        }

        ServiceBreak::create([
            'service_id' => $service->id,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'name' => 'Lunch break',
        ]);

        ServiceBreak::create([
            'service_id' => $service->id,
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'name' => 'Cleaning break',
        ]);

        $publicHoliday = Carbon::today()->addDays(2);

        ServiceClosure::create([
            'service_id' => $service->id,
            'start_datetime' => $publicHoliday->copy()->startOfDay(),
            'end_datetime' => $publicHoliday->copy()->endOfDay(),
            'reason' => 'Public holiday',
        ]);
    }
}
