<?php

namespace Tests;

use Carbon\Carbon;
use Database\Seeders\SchedulingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait SeedsScheduling
{
    use RefreshDatabase;

    protected function seedScheduling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-16 09:00:00'));
        $this->seed(SchedulingSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
