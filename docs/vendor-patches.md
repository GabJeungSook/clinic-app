# Vendor patches (re-apply after `composer update`)

We keep two small edits inside `vendor/nativephp/desktop`. They are **overwritten
whenever `nativephp/desktop` is reinstalled or updated** (`composer update`,
`composer install` on a clean vendor). After any such command, re-apply both.

> Tip: after re-applying, rebuild the desktop app so the change is bundled
> (`php artisan native:build win x64`, or `native:publish` when releasing).

---

## 1. Startup loading window (progress bar on launch)

**File:** `resources/electron/src/main/index.js`

A frameless, centered loading window with an animated progress bar is created on
`app.whenReady()` and closed when the real app window is ready. Without it, there
is a blank few seconds between double-clicking the icon and the window appearing.

Re-apply: add `import { app, BrowserWindow } from 'electron';`, the `loadingHtml`
string + `closeLoadingWindow()` helper, and the `app.whenReady().then(...)` block
that opens the loader and closes it on `browser-window-created` → `ready-to-show`.
(See git history for the exact block.)

---

## 2. Auto-updater: notify first, don't auto-download

**Files:**
- `resources/electron/electron-plugin/dist/server/api/autoUpdater.js`  ← the one that actually runs (bundled)
- `resources/electron/electron-plugin/src/server/api/autoUpdater.ts`   ← source, keep in sync

electron-updater defaults to `autoDownload = true`, which would download a new
release the instant the silent launch check finds one — unwanted on an
occasionally-online clinic PC, and it contradicts "nothing downloads until the
doctor clicks." We turn it off.

Re-apply: right after `const { autoUpdater } = electronUpdater;` add:

```js
autoUpdater.autoDownload = false;
```

The **dist** file is the one bundled into the build (the plugin is imported
prebuilt via `#plugin`); patch it. Patch the `.ts` too so the change is
discoverable if the plugin is ever recompiled.

With this off:
- launch check → `update-available` event → sidebar "Update available" badge (no download)
- user clicks **Download & install** → `downloadUpdate()` → progress → `update-downloaded`
- user clicks **Restart to update** → `quitAndInstall()`
