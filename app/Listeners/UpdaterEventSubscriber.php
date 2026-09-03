<?php

namespace App\Listeners;

use App\Support\Updater\UpdaterState;
use Illuminate\Events\Dispatcher;
use Native\Desktop\Events\AutoUpdater\CheckingForUpdate;
use Native\Desktop\Events\AutoUpdater\DownloadProgress;
use Native\Desktop\Events\AutoUpdater\Error;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Events\AutoUpdater\UpdateCancelled;
use Native\Desktop\Events\AutoUpdater\UpdateDownloaded;
use Native\Desktop\Events\AutoUpdater\UpdateNotAvailable;

/**
 * Translates the NativePHP auto-updater events into a simple, pollable status
 * (see UpdaterState). Registered in AppServiceProvider::boot().
 */
class UpdaterEventSubscriber
{
    public function onChecking(CheckingForUpdate $event): void
    {
        UpdaterState::set(['state' => 'checking', 'message' => null]);
    }

    public function onAvailable(UpdateAvailable $event): void
    {
        UpdaterState::set([
            'state' => 'available',
            'version' => $event->version,
            'notes' => $this->notes($event->releaseNotes),
        ]);
    }

    public function onNotAvailable(UpdateNotAvailable $event): void
    {
        UpdaterState::set([
            'state' => 'not-available',
            'version' => $event->version,
            'percent' => 0,
        ]);
    }

    public function onProgress(DownloadProgress $event): void
    {
        UpdaterState::set([
            'state' => 'downloading',
            'percent' => (int) round($event->percent),
        ]);
    }

    public function onDownloaded(UpdateDownloaded $event): void
    {
        UpdaterState::set([
            'state' => 'downloaded',
            'version' => $event->version,
            'notes' => $this->notes($event->releaseNotes),
            'percent' => 100,
        ]);
    }

    public function onCancelled(UpdateCancelled $event): void
    {
        UpdaterState::set(['state' => 'idle', 'percent' => 0]);
    }

    public function onError(Error $event): void
    {
        UpdaterState::set(['state' => 'error', 'message' => $event->message]);
    }

    /** Release notes may arrive as a string or an array of blocks. */
    private function notes(string|array|null $notes): ?string
    {
        if (is_array($notes)) {
            return collect($notes)->pluck('note')->filter()->implode("\n\n") ?: null;
        }

        return $notes ?: null;
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            CheckingForUpdate::class => 'onChecking',
            UpdateAvailable::class => 'onAvailable',
            UpdateNotAvailable::class => 'onNotAvailable',
            DownloadProgress::class => 'onProgress',
            UpdateDownloaded::class => 'onDownloaded',
            UpdateCancelled::class => 'onCancelled',
            Error::class => 'onError',
        ];
    }
}
