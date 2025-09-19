<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Limit migrations during tests to our application's migration path only
    // to avoid vendor (IFRS) migrations incompatible with SQLite.
    protected array $migrateFreshUsing = [
        '--path' => 'database/migrations',
    ];
}
