<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed reference data (roles and permissions) whenever RefreshDatabase
     * migrates, so every test starts from the same baseline.
     */
    protected bool $seed = true;
}
