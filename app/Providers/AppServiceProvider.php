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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
}
