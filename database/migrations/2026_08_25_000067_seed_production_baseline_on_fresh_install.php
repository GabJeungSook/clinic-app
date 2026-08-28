<?php

use Database\Seeders\ProductionSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh-install baseline for the packaged desktop app.
 *
 * The seeding used to run from NativeAppServiceProvider::boot(), but that
 * executes inside the built-in PHP server's request and calling a seeder there
 * crashes the server. Migrations, by contrast, run in NativePHP's own
 * `php artisan migrate --force` process (a normal CLI process) *before* the
 * server serves anything, which is the safe place to lay down the baseline.
 *
 * Runs at most once: only when the app is the packaged desktop build
 * (NATIVEPHP_RUNNING) and the database has no users yet. It is a no-op for the
 * dev environment and for any install that already has data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! env('NATIVEPHP_RUNNING')) {
            return; // dev / web context — never auto-seed here
        }

        if (! Schema::hasTable('users') || DB::table('users')->count() > 0) {
            return; // already provisioned
        }

        // Clean baseline every time: owner login + service/inventory catalogue.
        Artisan::call('db:seed', [
            '--class' => ProductionSeeder::class,
            '--force' => true,
        ]);

        // Presentation builds (CLINIC_SEED_DEMO=true) additionally get rich demo
        // data — patients, appointments, sales, treatments — layered on top.
        // Wrapped so a demo-seed hiccup can never break a real fresh install:
        // the clean baseline above is always in place.
        if (config('clinic.seed_demo')) {
            try {
                Artisan::call('db:seed', [
                    '--class' => SampleDataSeeder::class,
                    '--force' => true,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function down(): void
    {
        // Baseline data is not rolled back.
    }
};
