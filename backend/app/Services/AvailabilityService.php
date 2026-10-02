<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Service;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    // A new appointment may start every 30 minutes within working hours.
    private const SLOT_INTERVAL_MINUTES = 30;

    /**
     * Returns the bookable slots for one dentist, one service and one date.
     * Each slot looks like ['start_time' => '09:00', 'end_time' => '09:30'].
     *
     * $ignoreAppointmentId lets an existing appointment be treated as not there,
     * so it can be rescheduled without blocking itself.
     */
    public function getAvailableSlots(
        Dentist $dentist,
        Service $service,
        Carbon $date,
        ?int $ignoreAppointmentId = null
    ): array {
        // 1. Working hours for that weekday (0 = Sunday ... 6 = Saturday). No row = closed.
        $schedule = $dentist->schedules()
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $schedule) {
            return [];
        }

        $workStart = $this->toMinutes($schedule->start_time);
        $workEnd = $this->toMinutes($schedule->end_time);
        $duration = $service->duration_minutes;

        // 2. Time already taken on that date. Cancelled and no-show appointments don't block.
        $busy = Appointment::where('dentist_id', $dentist->id)
            ->where('appointment_date', $date->toDateString())
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->when($ignoreAppointmentId, fn ($query) => $query->where('id', '!=', $ignoreAppointmentId))
            ->get(['start_time', 'end_time'])
            ->map(fn ($appointment) => [
                $this->toMinutes($appointment->start_time),
                $this->toMinutes($appointment->end_time),
            ]);

        // 3. If the date is today, nothing at or before the current time is bookable.
        $earliestAllowed = null;
        if ($date->isToday()) {
            $now = Carbon::now();
            $earliestAllowed = $now->hour * 60 + $now->minute;
        }

        // 4. Walk through the working day and keep every start time that fits.
        $slots = [];

        for ($start = $workStart; $start + $duration <= $workEnd; $start += self::SLOT_INTERVAL_MINUTES) {
            $end = $start + $duration;

            if ($earliestAllowed !== null && $start <= $earliestAllowed) {
                continue;
            }

            // Two time ranges overlap when each one starts before the other ends.
            $overlaps = $busy->contains(
                fn ($range) => $start < $range[1] && $end > $range[0]
            );

            if (! $overlaps) {
                $slots[] = [
                    'start_time' => $this->toTimeString($start),
                    'end_time' => $this->toTimeString($end),
                ];
            }
        }

        return $slots;
    }

    // "09:30:00" -> 570 (minutes since midnight). Whole-number math avoids time-parsing bugs.
    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    }

    // 570 -> "09:30"
    private function toTimeString(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}