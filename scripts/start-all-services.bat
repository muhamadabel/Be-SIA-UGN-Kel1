@echo off
setlocal

REM ============================================================================
REM 1. DEFINE PATHS (Metode Waterfall - Prioritas)
REM ============================================================================

REM -- BACKEND PATHS --
set "BACKEND_DIR="
if exist "D:\SIA PAD\SIA-GLOBAL" set "BACKEND_DIR=D:\SIA PAD\SIA-GLOBAL"
if not defined BACKEND_DIR if exist "D:\College\3rd Semester\SIA-UGN\SIA-GLOBAL" set "BACKEND_DIR=D:\College\3rd Semester\SIA-UGN\SIA-GLOBAL"
if not defined BACKEND_DIR if exist "D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL" set "BACKEND_DIR=D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL"
if not defined BACKEND_DIR if exist "D:\coding\SIA-UGN\SIA_GLOBAL" set "BACKEND_DIR=D:\coding\SIA-UGN\SIA_GLOBAL"

REM -- FRONTEND PATHS --
set "FRONTEND_DIR="
if exist "D:\SIA PAD\FEmodulpresensi-usermanagemenSIA" set "FRONTEND_DIR=D:\SIA PAD\FEmodulpresensi-usermanagemenSIA"
if not defined FRONTEND_DIR if exist "D:\College\3rd Semester\SIA-UGN\FEmodulpresensi-usermanagemenSIA" set "FRONTEND_DIR=D:\College\3rd Semester\SIA-UGN\FEmodulpresensi-usermanagemenSIA"
if not defined FRONTEND_DIR if exist "D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\FEmodulpresensi-usermanagemenSIA" set "FRONTEND_DIR=D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\FEmodulpresensi-usermanagemenSIA"
if not defined FRONTEND_DIR if exist "D:\coding\SIA-UGN\fepadsiaugn" set "FRONTEND_DIR=D:\coding\SIA-UGN\fepadsiaugn"

REM -- VALIDATION --
if not defined BACKEND_DIR (
    echo [ERROR] Backend directory not found!
    pause
    exit /b
)
if not defined FRONTEND_DIR (
    echo [ERROR] Frontend directory not found!
    pause
    exit /b
)

echo.
echo ============================================================================
echo SIA PAD - Start All Services
echo.
echo Backend Directory: "%BACKEND_DIR%"
echo Frontend Directory: "%FRONTEND_DIR%"
echo.
echo 1. Laravel Backend (port 8000)
echo 2. Laravel Reverb WebSocket (port 9090)
echo 3. Laravel Scheduler (QR rotation every 30s)
echo 4. Laravel Queue Worker (background jobs)
echo 5. Node.js Frontend (port 3000)
echo.
echo ============================================================================
echo.
echo Press ENTER to start all services...
echo.
pause > nul

echo Starting services...

REM ============================================================================
REM 2. START SERVICES
REM Perhatikan: Menggunakan 'cmd /c' agar window close saat proses mati
REM ============================================================================

REM Start Laravel Backend
start "Laravel Backend" /D "%BACKEND_DIR%" cmd /c "php artisan serve --host 0.0.0.0 --port 8000"
timeout /t 2 > nul

REM Start Laravel Reverb
start "Laravel Reverb" /D "%BACKEND_DIR%" cmd /c "php artisan reverb:start --host 0.0.0.0 --port 9090"
timeout /t 2 > nul

REM Start Laravel Scheduler
start "Laravel Scheduler" /D "%BACKEND_DIR%" cmd /c "php artisan schedule:work"
timeout /t 2 > nul

REM Start Laravel Queue Worker
start "Laravel Queue Worker" /D "%BACKEND_DIR%" cmd /c "php artisan queue:work --tries=3 --timeout=60"
timeout /t 2 > nul

REM Start Node.js Frontend
start "Node.js Frontend" /D "%FRONTEND_DIR%" cmd /c "npm run dev"
timeout /t 2 > nul

echo.
echo ============================================================================
echo All services started! 
echo Press ESC to stop all services and close all windows.
echo ============================================================================

:wait_for_esc
powershell -Command "while ($true) { if ([Console]::KeyAvailable) { $key = [Console]::ReadKey($true); if ($key.Key -eq 'Escape') { exit 0 } } Start-Sleep -Milliseconds 100 }"
if %errorlevel% equ 0 goto stop_services
goto wait_for_esc

:stop_services
echo.
echo Stopping services and closing windows...

REM ============================================================================
REM 3. STOP SERVICES (The "Nuke" Method)
REM Ini akan mematikan prosesnya. Karena pakai 'cmd /c', window akan ikut nutup.
REM ============================================================================

echo Killing PHP processes...
taskkill /F /IM php.exe /T > nul 2>&1

echo Killing Node processes...
taskkill /F /IM node.exe /T > nul 2>&1

echo.
echo Cleanup complete.
timeout /t 2 > nul
exit