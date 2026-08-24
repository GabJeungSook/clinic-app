<?php

namespace App\Http\Controllers;

use App\Actions\System\BackupDatabase;
use App\Actions\System\RestoreDatabase;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClinicSettingsController extends Controller
{
    public function edit(): Response
    {
        $backupDir = storage_path('app/backups');
        $backups = File::isDirectory($backupDir)
            ? collect(File::files($backupDir))
                ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sqlite'))
                ->sortByDesc(fn ($f) => $f->getFilename())
                ->take(10)
                ->map(fn ($f) => [
                    'name' => $f->getFilename(),
                    'size_kb' => round($f->getSize() / 1024, 1),
                ])->values()
            : collect();

        return Inertia::render('ClinicSettings', [
            'settings' => Settings::all(),
            'backups' => $backups,
            'lastBackup' => Settings::get('backup.last_succeeded_at'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'clinic_name' => ['required', 'string', 'max:150'],
            'clinic_address' => ['nullable', 'string', 'max:255'],
            'clinic_phone' => ['nullable', 'string', 'max:50'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:8'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'tax_enabled' => ['boolean'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_inclusive' => ['boolean'],
            'expiry_threshold_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        Settings::set('clinic.name', $data['clinic_name'], 'clinic');
        Settings::set('clinic.address', $data['clinic_address'] ?? '', 'clinic');
        Settings::set('clinic.phone', $data['clinic_phone'] ?? '', 'clinic');
        Settings::set('clinic.receipt_footer', $data['receipt_footer'] ?? '', 'clinic');
        Settings::set('billing.currency', $data['currency'], 'billing');
        Settings::set('billing.currency_symbol', $data['currency_symbol'], 'billing');
        Settings::set('billing.tax_enabled', (bool) ($data['tax_enabled'] ?? false), 'billing');
        Settings::set('billing.tax_rate', (float) $data['tax_rate'], 'billing');
        Settings::set('billing.tax_inclusive', (bool) ($data['tax_inclusive'] ?? false), 'billing');
        Settings::set('inventory.expiry_threshold_days', (int) $data['expiry_threshold_days'], 'inventory');

        return back()->with('success', 'Settings saved.');
    }

    public function backupNow(BackupDatabase $backup): RedirectResponse
    {
        try {
            $path = $backup->handle();
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Backup created: ' . basename($path));
    }

    /** Download a stored backup (e.g. to copy it onto a USB drive). */
    public function downloadBackup(string $name): BinaryFileResponse
    {
        $name = basename($name); // guard against path traversal
        $path = storage_path('app/backups/' . $name);

        abort_unless(str_ends_with($name, '.sqlite') && File::exists($path), 404);

        return response()->download($path);
    }

    /**
     * Replace the live database with a chosen snapshot, or one imported from a
     * file. A safety backup is taken first; the user is then sent to sign in
     * again (database sessions are part of what gets restored).
     */
    public function restore(Request $request, RestoreDatabase $restore): RedirectResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:204800', 'required_without:name'],
        ]);

        if ($request->hasFile('file')) {
            $upload = $request->file('file');
            if (strtolower((string) $upload->getClientOriginalExtension()) !== 'sqlite') {
                return back()->with('error', 'Please choose a .sqlite backup file.');
            }
            $source = $upload->getRealPath();
        } else {
            $name = basename((string) $request->string('name'));
            $source = storage_path('app/backups/' . $name);
            if (! File::exists($source)) {
                return back()->with('error', 'That backup no longer exists.');
            }
        }

        try {
            $restore->handle($source);
        } catch (\Throwable $e) {
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }

        // Database-backed sessions were just replaced — force a clean sign-in.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Database restored. Please sign in again.');
    }
}
