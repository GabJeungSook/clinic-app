# Publishing updates (GitHub releases)

The app checks the **public** repo `GabJeungSook/clinic-app` for newer releases
and can download + install them from inside the app (Settings → Software update).
This only needs internet *at the moment of checking/downloading*; otherwise the
app runs fully offline.

## How it works

1. `native:publish` builds the Windows installer and uploads a GitHub Release
   (tagged `v<version>`) containing `...-setup.exe`, its `.blockmap`, and a
   `latest.yml` manifest.
2. Inside the app, the launch check (and the manual **Check for updates** button)
   reads `latest.yml`, compares it to the installed `NATIVEPHP_APP_VERSION`, and
   raises an "Update available" badge if newer.
3. The doctor clicks **Download & install** → progress bar → **Restart to update**,
   which relaunches the app on the new version.

Patient data is safe across updates: the SQLite DB lives in
`%APPDATA%\skinthera-medical-aesthetic\database\`, separate from the program
files. An update replaces only the app; migrations run automatically on the next
launch.

## One-time setup

- The repo is already public (no token ships in the app).
- Create a GitHub **personal access token** with `repo` / contents:write scope and
  put it in `.env` as `GITHUB_TOKEN=` (used only on this machine at publish time;
  it is **not** bundled into the app). `.env` is gitignored — never commit it.
- Confirm `.env` has `NATIVEPHP_UPDATER_ENABLED=true`, `NATIVEPHP_UPDATER_PROVIDER=github`,
  `GITHUB_OWNER=GabJeungSook`, `GITHUB_REPO=clinic-app`, `GITHUB_RELEASE_TYPE=release`.

## Cutting a release

1. Bump `NATIVEPHP_APP_VERSION` in `.env` (e.g. `1.5.0` → `1.5.1`).
2. Re-apply vendor patches if you just ran `composer update` (see `vendor-patches.md`).
3. Run:
   ```
   php artisan native:publish win x64
   ```
   This builds and creates a **published** (not draft) GitHub release. If it lands
   as a draft, publish it on GitHub — a public repo's updater cannot see drafts.
4. Verify the release page shows `...-setup.exe`, `...-setup.exe.blockmap`, and
   `latest.yml`.

## Important: the first updater build is manual

Version **1.5.0** is the first build that contains the updater. It must be
**installed by hand** on the clinic PC (the current 1.4.2 has no updater to
bootstrap from). Every release **after** 1.5.0 can update itself.

## Notes

- No code signing yet: Windows SmartScreen warns "unknown publisher" on the manual
  install step. Auto-update still works. A real cert (Azure/EV) removes the warning
  later — optional.
- The updater is desktop-only. Under `composer run dev` in a browser, the
  Settings buttons show a friendly "only available in the installed app" message.
