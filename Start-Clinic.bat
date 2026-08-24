@echo off
title Aesthetic Clinic System
cd /d "%~dp0"

echo ============================================
echo   Aesthetic Clinic Management System
echo ============================================
echo.
echo Starting the local server...  (keep this window open)
echo The app will open in your browser at http://127.0.0.1:8000
echo.
echo To stop the clinic system, close this window.
echo.

REM Open the browser a moment after the server starts.
start "" /b cmd /c "timeout /t 2 >nul & start http://127.0.0.1:8000"

REM Run the Laravel app on the local machine only (offline).
php artisan serve --host=127.0.0.1 --port=8000

pause
