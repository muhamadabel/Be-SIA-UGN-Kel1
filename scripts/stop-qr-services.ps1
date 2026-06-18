# Stop QR/Chat/Scheduler/Queue/Frontend services
# Safely stops processes started by the launcher by matching command line patterns.

param(
  [switch]$Force
)

function Stop-ByCommandLine {
  param(
    [string]$pattern,
    [string]$label
  )
  try {
    $procs = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like $pattern }
    if ($procs) {
      foreach ($p in $procs) {
        Write-Host "Stopping $label (PID: $($p.ProcessId))" -ForegroundColor Yellow
        if ($Force) {
          Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue
        } else {
          Stop-Process -Id $p.ProcessId -ErrorAction SilentlyContinue
        }
      }
    } else {
      Write-Host "No running process matched: $label" -ForegroundColor DarkGray
    }
  } catch {
    Write-Host "Error stopping ${label}: $($_.Exception.Message)" -ForegroundColor Red
  }
}

Write-Host "\nStopping QR/Chat/Scheduler/Queue/Frontend services..." -ForegroundColor Cyan

# Laravel Backend (artisan serve)
Stop-ByCommandLine -pattern '*artisan serve*' -label 'Laravel Backend'

# Laravel Reverb WebSocket
Stop-ByCommandLine -pattern '*artisan reverb:start*' -label 'Laravel Reverb'

# Laravel Scheduler
Stop-ByCommandLine -pattern '*artisan schedule:work*' -label 'Laravel Scheduler'

# Laravel Queue Worker
Stop-ByCommandLine -pattern '*artisan queue:work*' -label 'Laravel Queue Worker'

# Node.js Frontend (npm run dev / vite / next / webpack dev server)
# Try common patterns
Stop-ByCommandLine -pattern '*npm* run dev*' -label 'Node Frontend (npm run dev)'
Stop-ByCommandLine -pattern '*node* vite*' -label 'Node Frontend (vite)'
Stop-ByCommandLine -pattern '*node* next* dev*' -label 'Node Frontend (next dev)'
Stop-ByCommandLine -pattern '*node* webpack* dev*' -label 'Node Frontend (webpack dev)'

Write-Host "\nDone. If some windows/tabs remain, close them or rerun with -Force." -ForegroundColor Green
