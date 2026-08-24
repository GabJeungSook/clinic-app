<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Binds the application TestCase and a fresh database to every test in the
| Feature and Unit suites, so Pest-style tests work alongside the class-based
| tests shipped by the starter kit.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Unit');
