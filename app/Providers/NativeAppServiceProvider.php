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
        // 1. Show a lightweight splash immediately so the user has visible
        //    feedback while the app finishes starting. It loads a self-contained
        //    data: URL (no PHP round-trip), so it renders even while the database
        //    work below is briefly blocking the built-in PHP server.
        Window::open('splash')
            ->url($this->splashUrl())
            ->width(460)
            ->height(300)
            ->frameless()
            ->hasShadow(true)
            ->resizable(false)
            ->alwaysOnTop();

        // 2. Bring the database up to date (fast on a normal launch — the slow
        //    safety backup only runs when the app was actually updated).
        $this->prepareDatabase();

        // 3. Open the real app window — no menu bar (removes the File/Edit/View
        //    toolbar), maximised, remembering its size/position between launches.
        Window::open('main')
            ->title('Skinthera Medical Aesthetic')
            ->hideMenu()
            ->rememberState()
            ->maximized();

        // 4. Dismiss the splash now that the main window is taking over.
        Window::close('splash');
    }

    /**
     * Keep the user's appData database current and, on a fresh install, lay down
     * the baseline data.
     *
     * The safety backup (a full copy of the clinic's only database) is expensive,
     * so it is taken ONLY when the app version changed — i.e. when an update might
     * run a new migration. Same-version launches skip it entirely, which is what
     * keeps everyday startups fast.
     */
    private function prepareDatabase(): void
    {
        try {
            $versionFile = storage_path('app/.app_version');
            $currentVersion = (string) config('nativephp.version');
            $lastVersion = is_file($versionFile) ? trim((string) @file_get_contents($versionFile)) : null;

            // A different stored version means the user just updated the app.
            $isUpdate = $lastVersion !== null && $lastVersion !== $currentVersion;

            if ($isUpdate && Schema::hasTable('migrations')) {
                Artisan::call('clinic:backup');
            }

            Artisan::call('migrate', ['--force' => true]);

            // Fresh database → seed the demo-free production baseline once.
            if (User::query()->count() === 0) {
                Artisan::call('db:seed', ['--class' => ProductionSeeder::class, '--force' => true]);
            }

            @file_put_contents($versionFile, $currentVersion);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * A self-contained branded splash page (no external assets, no server call)
     * shown while the app starts.
     */
    private function splashUrl(): string
    {
        $html = <<<'HTML'
<!doctype html><html><head><meta charset="utf-8"><style>
  html,body{margin:0;height:100%;font-family:'Segoe UI',system-ui,sans-serif;
    background:linear-gradient(160deg,#ffffff,#fdf2f8);color:#9d174d;
    display:flex;align-items:center;justify-content:center;overflow:hidden;user-select:none}
  .wrap{text-align:center}
  .ring{width:52px;height:52px;margin:0 auto 20px;border:4px solid #f3d3e0;
    border-top-color:#be185d;border-radius:50%;animation:spin .9s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  h1{font-size:19px;font-weight:800;margin:0 0 6px;letter-spacing:-.01em}
  p{margin:0;font-size:12px;color:#9ca3af}
</style></head><body>
  <div class="wrap">
    <div class="ring"></div>
    <h1>Skinthera Medical Aesthetic</h1>
    <p>Starting up, please wait…</p>
  </div>
</body></html>
HTML;

        return 'data:text/html;charset=utf-8,'.rawurlencode($html);
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
