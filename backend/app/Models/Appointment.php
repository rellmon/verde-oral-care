<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    // Online cancellation closes this many hours before the appointment starts.
    public const MIN_HOURS_BEFORE_CANCEL = 24;

    // Which status an appointment may move to next. Completed, cancelled and no-show are final.
    public const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_COMPLETED, self::STATUS_NO_SHOW, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_NO_SHOW => [],
    ];

    protected $fillable = [
        'patient_id',
        'dentist_id',
        'service_id',
        'appointment_date',
        'start_time',
        'end_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // The moment the appointment begins, e.g. 2026-10-08 09:00:00.
    public function startsAt(): Carbon
    {
        return Carbon::parse($this->appointment_date->toDateString().' '.$this->start_time);
    }

    // The single place that decides whether a patient can still cancel online.
    public function canBeCancelled(): bool
    {
        $isActive = in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);

        return $isActive
            && $this->startsAt()->greaterThan(now()->addHours(self::MIN_HOURS_BEFORE_CANCEL));
    }

    // Returns why the appointment cannot move to $newStatus, or null if the change is allowed.
    public function statusChangeError(string $newStatus): ?string
    {
        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return 'A '.str_replace('_', '-', $this->status).' appointment cannot be changed to '
                .str_replace('_', '-', $newStatus).'.';
        }

        $needsStarted = in_array($newStatus, [self::STATUS_COMPLETED, self::STATUS_NO_SHOW], true);

        if ($needsStarted && $this->startsAt()->isFuture()) {
            return 'This appointment has not started yet, so it cannot be marked as '
                .str_replace('_', '-', $newStatus).'.';
        }

        return null;
    }

    // The statuses this appointment can move to right now (used to show the right buttons).
    public function availableStatuses(): array
    {
        return array_values(array_filter(
            self::STATUS_TRANSITIONS[$this->status] ?? [],
            fn ($status) => $this->statusChangeError($status) === null
        ));
    }
}