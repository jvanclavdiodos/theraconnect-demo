# TheraConnect — one-click demo connect.
# Re-establishes the USB tunnel (phone 127.0.0.1:8080 -> PC 8080) and launches
# the app. The app does NOT use the Wi-Fi/hotspot IP, so this works no matter
# what the hotspot hands out. Run this before the demo, or any time the phone
# was unplugged / rebooted (which drops the tunnel).
#
# Prereqs: Docker backend up (docker compose up -d), phone plugged in via USB
# with USB debugging on.

$ErrorActionPreference = 'Stop'
$adb = Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'
if (-not (Test-Path $adb)) { Write-Host "adb not found at $adb" -ForegroundColor Red; exit 1 }

& $adb start-server | Out-Null

# Require a connected device (status 'device', not 'unauthorized'/'offline').
$connected = (& $adb devices) | Select-String "\tdevice$"
if (-not $connected) {
    Write-Host "No authorized phone over USB." -ForegroundColor Yellow
    Write-Host "  -> Plug in the phone, unlock it, enable USB debugging, accept the RSA prompt." -ForegroundColor Yellow
    & $adb devices
    exit 1
}

# 1) Backend reachable on the PC?
try {
    $h = Invoke-RestMethod -Uri 'http://localhost:8080/api/v1/health' -TimeoutSec 5
    Write-Host "Backend: $($h.status)" -ForegroundColor Green
} catch {
    Write-Host "Backend not responding on localhost:8080 — start it with: docker compose up -d" -ForegroundColor Red
    exit 1
}

# 2) Tunnel.
& $adb reverse tcp:8080 tcp:8080 | Out-Null
Write-Host -NoNewline "Tunnel: "; (& $adb reverse --list) -replace '^', ''

# 3) Confirm the phone can reach the API through the tunnel.
$probe = & $adb shell "curl -s -m 5 -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/api/v1/health"
if ($probe -eq '200') { Write-Host "Phone -> backend: OK (200)" -ForegroundColor Green }
else { Write-Host "Phone -> backend probe returned '$probe'" -ForegroundColor Yellow }

# 4) Launch the app.
& $adb shell monkey -p com.theraconnect.app -c android.intent.category.LAUNCHER 1 | Out-Null
Write-Host "App launched. Demo ready." -ForegroundColor Green
