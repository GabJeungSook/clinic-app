<?php

namespace App\Providers;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        $this->prepareDatabase();

        Window::open()
            ->title('Skinthera Medical Aesthetic')
            ->rememberState()
            ->maximized();
    }

    /**
     * Keep the user's appData database current and, on a fresh install, lay down
     * the baseline data. A safety backup is taken before any schema change since
     * this one PC holds the clinic's only copy of the data.
     */
    private function prepareDatabase(): void
    {
        try {
            // Existing install → snapshot before we touch the schema.
            if (Schema::hasTable('migrations')) {
                Artisan::call('clinic:backup');
            }

            Artisan::call('migrate', ['--force' => true]);

            // Fresh database → seed the demo-free production baseline once.
            if (User::query()->count() === 0) {
                Artisan::call('db:seed', ['--class' => ProductionSeeder::class, '--force' => true]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
