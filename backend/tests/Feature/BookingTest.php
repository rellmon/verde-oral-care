<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Dentist $dentist;

    private Patient $patient;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock on a Thursday morning, so "Monday 2026-10-05" is always the next Monday
        // and "today" never changes while the tests run.
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

        $this->dentist = Dentist::factory()->withStandardSchedule()->create(); // Mon-Fri 9-5, Sat 9-1
        $this->patient = Patient::factory()->create();
        $this->service = Service::factory()->create(); // 30 minutes
    }

    // A valid booking request for Monday 2026-10-05 at 10:00. Tests override what they want to change.
    private function bookingData(array $overrides = []): array
    {
        return array_merge([
            'dentist_id' => $this->dentist->id,
            'service_id' => $this->service->id,
            'appointment_date' => '2026-10-05',
            'start_time' => '10:00',
            'reason' => 'Toothache',
        ], $overrides);
    }

    public function test_a_patient_can_book_a_free_slot(): void
    {
        Sanctum::actingAs($this->patient->user);

        $this->postJson('/api/appointments', $this->bookingData())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.appointment_date', '2026-10-05')
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.end_time', '10:30');

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'service_id' => $this->service->id,
            'appointment_date' => '2026-10-05',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'pending',
        ]);
    }

    public function test_the_server_decides_the_patient_status_and_end_time(): void
    {
        $someoneElse = Patient::factory()->create();
        Sanctum::actingAs($this->patient->user);

        // A client tries to book as another patient, force a status, and pick its own end time.
        $this->postJson('/api/appointments', $this->bookingData([
            'patient_id' => $someoneElse->id,
            'status' => 'confirmed',
            'end_time' => '23:00',
        ]))->assertCreated();

        $appointment = Appointment::sole();

        $this->assertEquals($this->patient->id, $appointment->patient_id);
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('10:30:00', $appointment->end_time);
    }

    public function test_the_same_slot_cannot_be_booked_twice(): void
    {
        Sanctum::actingAs($this->patient->user);
        $this->postJson('/api/appointments', $this->bookingData())->assertCreated();

        $anotherPatient = Patient::factory()->create();
        Sanctum::actingAs($anotherPatient->user);

        $this->postJson('/api/appointments', $this->bookingData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $this->assertSame(1, Appointment::count());
    }

    public function test_overlapping_appointments_are_refused_but_back_to_back_ones_are_allowed(): void
    {
        $longService = Service::factory()->lasting(60)->create();

        Sanctum::actingAs($this->patient->user);
        $this->postJson('/api/appointments', $this->bookingData(['service_id' => $longService->id]))
            ->assertCreated(); // 10:00 to 11:00

        $anotherPatient = Patient::factory()->create();
        Sanctum::actingAs($anotherPatient->user);

        // 10:30 to 11:30 overlaps the first appointment.
        $this->postJson('/api/appointments', $this->bookingData(['service_id' => $longService->id, 'start_time' => '10:30']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        // 11:00 to 12:00 starts exactly when the first one ends, which is fine.
        $this->postJson('/api/appointments', $this->bookingData(['service_id' => $longService->id, 'start_time' => '11:00']))
            ->assertCreated();

        $this->assertSame(2, Appointment::count());
    }

    public function test_a_cancelled_appointment_frees_its_slot(): void
    {
        Appointment::factory()->for($this->dentist)->create([
            'appointment_date' => '2026-10-05',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($this->patient->user);

        $this->postJson('/api/appointments', $this->bookingData())->assertCreated();
    }

    public function test_the_availability_list_no_longer_offers_a_booked_slot(): void
    {
        Sanctum::actingAs($this->patient->user);
        $this->postJson('/api/appointments', $this->bookingData())->assertCreated();

        $url = "/api/dentists/{$this->dentist->id}/availability?service_id={$this->service->id}&date=2026-10-05";
        $starts = collect($this->getJson($url)->assertOk()->json('data'))->pluck('start_time');

        $this->assertTrue($starts->contains('09:30'));
        $this->assertFalse($starts->contains('10:00'));
        $this->assertTrue($starts->contains('10:30'));
    }

    /**
     * @param  array<string, string>  $overrides  what to change in a valid booking
     * @param  string  $errorField  the field the error must be reported on
     */
    #[DataProvider('invalidBookings')]
    public function test_invalid_bookings_are_refused(array $overrides, string $errorField): void
    {
        Sanctum::actingAs($this->patient->user);

        $this->postJson('/api/appointments', $this->bookingData($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);

        $this->assertSame(0, Appointment::count());
    }

    public static function invalidBookings(): array
    {
        return [
            'date in the past' => [['appointment_date' => '2026-09-30'], 'appointment_date'],
            'closed on Sunday' => [['appointment_date' => '2026-10-04'], 'start_time'],
            'not on the 30-minute grid' => [['start_time' => '10:15'], 'start_time'],
            'before opening time' => [['start_time' => '08:30'], 'start_time'],
            'after closing time' => [['start_time' => '17:00'], 'start_time'],
            'Saturday after closing' => [['appointment_date' => '2026-10-03', 'start_time' => '13:00'], 'start_time'],
            'earlier today' => [['appointment_date' => '2026-10-01', 'start_time' => '09:30'], 'start_time'],
            'missing reason' => [['reason' => ''], 'reason'],
            'badly formatted time' => [['start_time' => '10am'], 'start_time'],
        ];
    }

    public function test_an_inactive_dentist_or_service_cannot_be_booked(): void
    {
        $inactiveDentist = Dentist::factory()->inactive()->withStandardSchedule()->create();
        $inactiveService = Service::factory()->inactive()->create();

        Sanctum::actingAs($this->patient->user);

        $this->postJson('/api/appointments', $this->bookingData(['dentist_id' => $inactiveDentist->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dentist_id']);

        $this->postJson('/api/appointments', $this->bookingData(['service_id' => $inactiveService->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_guests_cannot_book(): void
    {
        $this->postJson('/api/appointments', $this->bookingData())->assertUnauthorized();

        $this->assertSame(0, Appointment::count());
    }

    public function test_admins_and_dentists_cannot_book(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->dentist()->create()] as $staff) {
            Sanctum::actingAs($staff);

            $this->postJson('/api/appointments', $this->bookingData())->assertForbidden();
        }

        $this->assertSame(0, Appointment::count());
    }

    public function test_a_patient_account_without_a_patient_profile_is_refused(): void
    {
        // A user with the patient role but no row in the patients table.
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/appointments', $this->bookingData())->assertForbidden();
    }
}
