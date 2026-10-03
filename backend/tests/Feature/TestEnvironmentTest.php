<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_against_the_separate_test_database(): void
    {
        $this->assertSame('verde_oral_care_test', DB::connection()->getDatabaseName());
    }

    public function test_the_test_database_starts_empty(): void
    {
        // The development database has seeded users. The test database must not.
        $this->assertSame(0, User::count());
    }
}
