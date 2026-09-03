<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\AutoUpdater;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has booted (via an internal HTTP
     * request to the built-in PHP server).
     *
     * IMPORTANT: this runs *inside* a request handled by PHP's single-process
     * built-in server (`php -S`). Do NOT run console work here — calling
     * `Artisan::call('migrate')` / `db:seed` from this request hard-crashes the
     * server process (the window then never appears). NativePHP already runs
     * `php artisan migrate --force` in its own startup process before serving,
     * and fresh-install seeding is handled by a migration, so there is nothing
     * database-related to do here. Keep this method fast: just open the window.
     */
    public function boot(): void
    {
        try {
            // The startup loading window (with progress bar) is handled at the
            // Electron level so it appears the instant the app launches, before
            // PHP is even serving — see the desktop runtime's main entry. Here we
            // just open the app's main window once PHP has booted.
            Window::open('main')
                ->title('Skinthera Medical Aesthetic')
                ->hideMenu()          // removes the File/Edit/View menu bar
                ->rememberState()     // restore size/position between launches
                ->width(1280)
                ->height(800);
        } catch (\Throwable $e) {
            report($e);
        }

        // Silently check for a newer release on launch. With autoDownload off
        // (electron patch) this only *notifies* — the sidebar badge lights up and
        // nothing downloads until the user clicks. A lightweight HTTP post to the
        // Electron API, not DB/console work, so it's safe here. Never let a failed
        // check (e.g. offline) block the window from opening.
        try {
            if (config('nativephp.updater.enabled')) {
                AutoUpdater::checkForUpdates();
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
