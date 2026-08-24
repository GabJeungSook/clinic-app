@echo off
title Aesthetic Clinic - First-time setup
cd /d "%~dp0"

echo ============================================
echo   Clinic System - First-time setup
echo ============================================
echo.

REM Ensure the SQLite database file exists.
if not exist "database\database.sqlite" (
    echo Creating database file...
    type nul > "database\database.sqlite"
)

echo Applying database migrations and seeding starter data...
php artisan migrate --force --seed

echo.
echo Building the interface...
call npm run build

echo.
echo ============================================
echo   Setup complete.
echo   Username:  admin
echo   Password:  password
echo.
echo   Now run  Start-Clinic.bat  to launch.
echo ============================================
pause
