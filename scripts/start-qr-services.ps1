# Launcher for QR/Chat/Scheduler/Queue/Frontend with selectable shell
param(
  [ValidateSet('cmd','powershell','wt')]
  [string]$shell = 'powershell'
)

function Launch-CmdWindow($title, $dir, $command) {
  Start-Process -FilePath cmd.exe -ArgumentList '/k', "cd /d `"$dir`" & $command" -WindowStyle Normal
  Start-Sleep -Seconds 2
}

function Launch-PowerShellWindow($title, $dir, $command) {
  Start-Process -FilePath powershell.exe -ArgumentList '-NoExit','-Command',"Set-Location `"$dir`"; $command" -WindowStyle Normal
  Start-Sleep -Seconds 2
}

function Launch-WindowsTerminalTabs() {
  $cmds = @(
    @{ title = 'Laravel Backend'; dir = 'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL'; cmd = 'php artisan serve --host 0.0.0.0 --port 8000' },
    @{ title = 'Laravel Reverb';  dir = 'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL'; cmd = 'php artisan reverb:start --host 0.0.0.0 --port 9090' },
    @{ title = 'Scheduler';       dir = 'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL'; cmd = 'php artisan schedule:work' },
    @{ title = 'Queue Worker';    dir = 'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL'; cmd = 'php artisan queue:work --tries=3 --timeout=60' },
    @{ title = 'Frontend';        dir = 'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\FEmodulpresensi-usermanagemenSIA'; cmd = 'npm run dev' }
  )

  $segments = @()
  foreach ($c in $cmds) {
    $segments += 'new-tab -d "' + $c.dir + '" --title "' + $c.title + '" powershell -NoExit -Command "' + $c.cmd + '"'
  }

  $full = ($segments -join ' ; ')
  Start-Process -FilePath wt.exe -ArgumentList $full
}

Write-Host "`nStarting All Services (shell: $shell)" -ForegroundColor Cyan

switch ($shell) {
  'cmd' {
    Launch-CmdWindow 'Laravel Backend' 'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan serve'
    Launch-CmdWindow 'Laravel Reverb'  'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan reverb:start'
    Launch-CmdWindow 'Scheduler'       'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan schedule:work'
    Launch-CmdWindow 'Queue Worker'    'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan queue:work --tries=3 --timeout=60'
    Launch-CmdWindow 'Frontend'        'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\FEmodulpresensi-usermanagemenSIA' 'npm run dev'
  }
  'powershell' {
    Launch-PowerShellWindow 'Laravel Backend' 'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan serve'
    Launch-PowerShellWindow 'Laravel Reverb'  'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan reverb:start'
    Launch-PowerShellWindow 'Scheduler'       'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan schedule:work'
    Launch-PowerShellWindow 'Queue Worker'    'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\SIA-GLOBAL' 'php artisan queue:work --tries=3 --timeout=60'
    Launch-PowerShellWindow 'Frontend'        'D:\Project\Coding\WEB\SIA GLOBAL NUSANTARA\FEmodulpresensi-usermanagemenSIA' 'npm run dev'
  }
  'wt' {
    Launch-WindowsTerminalTabs
  }
  'zhafir' {
    Launch-CmdWindow 'Laravel Backend' 'D:\College\3rd Semester\SIA-UGN\SIA-GLOBAL' 'php artisan serve --host 0.0.0.0 --port 8000'
    Launch-CmdWindow 'Laravel Reverb'  'D:\College\3rd Semester\SIA-UGN\SIA-GLOBAL' 'php artisan reverb:start --host 0.0.0.0 --port 9090'
    Launch-CmdWindow 'Scheduler'       'D:\College\3rd Semester\SIA-UGN\SIA-GLOBAL' 'php artisan schedule:work'
    Launch-CmdWindow 'Queue Worker'    'D:\College\3rd Semester\SIA-UGN\SIA-GLOBAL' 'php artisan queue:work --tries=3 --timeout=60'
    Launch-CmdWindow 'Frontend'        'D:\College\3rd Semester\SIA-UGN\FEmodulpresensi-usermanagemenSIA' 'npm run dev'
  }
}

Write-Host "All services started. Keep windows/tabs open." -ForegroundColor Green
# QR + Chat + Scheduler + Queue + Frontend launcher (PowerShell)
# Run: & "D:\College\3rd Semester\SIA-UGN\SIA-GLOBAL\start-qr-services.ps1"

Write-Host "`n============================================================================" -ForegroundColor Cyan
Write-Host "Starting All Services (PowerShell)" -ForegroundColor Cyan
Write-Host "============================================================================`n" -ForegroundColor Cyan
Write-Host "This will open 5 separate PowerShell windows:" -ForegroundColor Yellow
Write-Host "  1. Laravel Backend (port 8000)" -ForegroundColor Yellow
Write-Host "  2. Laravel Reverb WebSocket (port 9090)" -ForegroundColor Yellow
Write-Host "  3. Laravel Scheduler (QR rotation every 30s)" -ForegroundColor Yellow
Write-Host "  4. Laravel Queue Worker (background jobs)" -ForegroundColor Yellow
Write-Host "  5. Node.js Frontend (port 3000)" -ForegroundColor Yellow

Write-Host "`n============================================================================" -ForegroundColor Cyan
Write-Host "All services started! Keep all windows open." -ForegroundColor Cyan
Write-Host "============================================================================`n" -ForegroundColor Cyan

