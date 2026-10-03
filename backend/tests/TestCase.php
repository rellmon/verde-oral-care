<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Runs right after Laravel boots and before any trait (such as RefreshDatabase) can touch a database.
    protected function refreshApplication()
    {
        parent::refreshApplication();

        // Ask Laravel which database it will really use. Environment variables are not a safe thing
        // to check: the same setting can be read from several places, and they can disagree.
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        // RefreshDatabase empties the database it runs against, so refuse to run
        // unless the name ends in _test (or it is a throwaway in-memory database).
        if (! str_ends_with($database, '_test') && $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]. The test database name must end in _test."
            );
        }
    }
}