<?php

namespace App\Providers;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Service;
use App\Models\TreatmentCourse;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Listeners\UpdaterEventSubscriber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Stable polymorphic aliases so ledger/invoice references stay readable
        // and portable when the schema moves online. Non-enforcing: models not
        // listed (framework internals) still fall back to their class name.
        Relation::morphMap([
            'user' => User::class,
            'patient' => Patient::class,
            'service' => Service::class,
            'inventory_item' => InventoryItem::class,
            'treatment_course' => TreatmentCourse::class,
            'treatment_session' => TreatmentSession::class,
            'invoice' => Invoice::class,
            'purchase' => Purchase::class,
            'purchase_item' => PurchaseItem::class,
        ]);

        // Record auto-updater progress into a pollable status (desktop only).
        Event::subscribe(UpdaterEventSubscriber::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $this->configureSqlite();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Keep SQLite's transaction statement-journals and temporary b-trees in
     * memory. A constraint-heavy INSERT inside a transaction (e.g. a stock
     * movement, with several foreign keys) otherwise makes SQLite open a
     * temporary file; under the PHP built-in server on Windows that temp path
     * isn't writable, surfacing as "SQLSTATE[HY000]: 14 unable to open database
     * file". temp_store=MEMORY removes the temp file entirely. Applied per
     * connection so it also covers the packaged desktop build.
     */
    protected function configureSqlite(): void
    {
        $default = config('database.default');
        if (config("database.connections.{$default}.driver") !== 'sqlite') {
            return;
        }

        try {
            DB::statement('PRAGMA temp_store = MEMORY');
        } catch (\Throwable) {
            // DB not reachable yet (e.g. first boot before migrate) — ignore.
        }
    }
}
