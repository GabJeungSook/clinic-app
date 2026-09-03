<?php

namespace App\Support\Updater;

use Illuminate\Support\Facades\Cache;

/**
 * Shared, cache-backed snapshot of the auto-updater's progress.
 *
 * The Electron auto-updater reports progress by POSTing NativePHP events to a
 * *separate* internal HTTP request (`/_native/api/events`), while the settings
 * UI polls from *another* request. Neither shares memory, so we bridge them
 * through the database cache store (CACHE_STORE=database). One key, last write
 * wins — there is only ever one update running on the single clinic machine.
 */
class UpdaterState
{
    private const KEY = 'updater.status';

    /** The shape returned before anything has happened. */
    public static function default(): array
    {
        return [
            // idle | checking | available | downloading | downloaded | not-available | error
            'state' => 'idle',
            'version' => null,   // the newer version, once known
            'percent' => 0,      // download progress, 0–100
            'notes' => null,     // release notes text, if provided
            'message' => null,   // human-readable message (used for errors)
            'checked_at' => null, // ISO-8601 timestamp of the last change
        ];
    }

    public static function get(): array
    {
        return array_merge(self::default(), Cache::get(self::KEY, []));
    }

    /** Merge a patch into the stored status and stamp the time. */
    public static function set(array $patch): array
    {
        $status = array_merge(self::get(), $patch, [
            'checked_at' => now()->toIso8601String(),
        ]);

        Cache::put(self::KEY, $status, now()->addHours(6));

        return $status;
    }
}
