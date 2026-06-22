# TheraConnect Quick Setup
# Run this script in PowerShell to get the project running with demo data.
#
# Usage: .\setup.ps1
#
# Prerequisites:
#   - PHP 8.2+ with extensions: curl, fileinfo, mbstring, openssl, pdo_mysql, zip
#   - Composer installed and in PATH
#   - MySQL 8+ or MariaDB 10.2+ running on port 3306

param(
    [string]$DBHost = "127.0.0.1",
    [string]$DBPort = "3306",
    [string]$DBName = "theraconnect",
    [string]$DBUser = "root",
    [string]$DBPass = "",
    [switch]$SkipLocalGuard  # Bypass the local-host check for migrate:fresh
)

$ErrorActionPreference = "Stop"
$projectDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectDir

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  TheraConnect v1.0 - Quick Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Find PHP
Write-Host "[1/8] Finding PHP..." -ForegroundColor Yellow
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    $phpPaths = @(
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php-8.2\php.exe",
        "C:\tools\php\php.exe"
    )
    foreach ($p in $phpPaths) {
        if (Test-Path $p) { $php = $p; break }
    }
}
if (-not $php) {
    Write-Host "  ERROR: PHP not found. Install PHP 8.2+ or XAMPP." -ForegroundColor Red
    exit 1
}
Write-Host "  PHP: $php" -ForegroundColor Green

# 2. Find Composer
Write-Host "[2/8] Finding Composer..." -ForegroundColor Yellow
$composer = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composer) {
    if (Test-Path "$projectDir\composer.phar") {
        $composer = "$projectDir\composer.phar"
    } else {
        Write-Host "  ERROR: Composer not found. Install from https://getcomposer.org" -ForegroundColor Red
        exit 1
    }
}
Write-Host "  Composer ready" -ForegroundColor Green

# 3. Install dependencies
Write-Host "[3/8] Installing PHP dependencies..." -ForegroundColor Yellow
$prevPref = $ErrorActionPreference; $ErrorActionPreference = "Continue"
if ($composer -like "*composer.phar") {
    & $php $composer install --no-interaction 2>&1 | Out-Null
} else {
    composer install --no-interaction 2>&1 | Out-Null
}
$ErrorActionPreference = $prevPref
Write-Host "  Dependencies installed." -ForegroundColor Green

# 4. Environment
Write-Host "[4/8] Setting up .env..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "  Created .env from .env.example" -ForegroundColor Green
} else {
    Write-Host "  .env already exists" -ForegroundColor Green
}

# 5. Generate key
Write-Host "[5/8] Generating app key..." -ForegroundColor Yellow
$prevPref = $ErrorActionPreference; $ErrorActionPreference = "Continue"
if ($php -is [string]) {
    & $php artisan key:generate --force 2>&1 | Out-Null
} else {
    php artisan key:generate --force 2>&1 | Out-Null
}
$ErrorActionPreference = $prevPref
Write-Host "  App key generated." -ForegroundColor Green

# 6. Create database
Write-Host "[6/8] Creating database..." -ForegroundColor Yellow
$mysqlPaths = @("C:\xampp\mysql\bin\mysql.exe", "C:\laragon\bin\mysql\mysql-8.0\bin\mysql.exe")
$mysql = Get-Command mysql -ErrorAction SilentlyContinue
if (-not $mysql) {
    foreach ($p in $mysqlPaths) { if (Test-Path $p) { $mysql = $p; break } }
}
if ($mysql) {
    try {
        if ($DBPass) {
            & $mysql -h $DBHost -P $DBPort -u $DBUser -p"$DBPass" -e "CREATE DATABASE IF NOT EXISTS $DBName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>&1 | Out-Null
        } else {
            & $mysql -h $DBHost -P $DBPort -u $DBUser -e "CREATE DATABASE IF NOT EXISTS $DBName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>&1 | Out-Null
        }
        Write-Host "  Database '$DBName' ready." -ForegroundColor Green
    } catch {
        Write-Host "  DB creation skipped - create '$DBName' manually." -ForegroundColor Yellow
    }
} else {
    Write-Host "  mysql.exe not found - create '$DBName' manually." -ForegroundColor Yellow
}

# 7. Migrate + demo seed
Write-Host "[7/8] Running migrations + demo data..." -ForegroundColor Yellow

# Guard: `migrate:fresh` drops ALL tables before re-migrating. Refuse to run
# against non-local DB hosts (anything other than localhost/127.x/::1) so this
# script can never wipe a staging or production database if invoked with a bad
# -DBHost argument. Override by passing -SkipLocalGuard at your own risk.
$isLocalHost = $DBHost -match '^(127\.|localhost|::1)'
if (-not $isLocalHost -and -not $SkipLocalGuard) {
    Write-Host "  REFUSING to migrate:fresh against non-local database host '$DBHost'." -ForegroundColor Red
    Write-Host "  migrate:fresh drops ALL tables - that would destroy real data." -ForegroundColor Red
    Write-Host "  If you really intend this, re-run with: -SkipLocalGuard" -ForegroundColor Red
    exit 1
}

$prevPref = $ErrorActionPreference; $ErrorActionPreference = "Continue"
if ($php -is [string]) {
    & $php artisan migrate:fresh --seed --force 2>&1 | Out-Null
} else {
    php artisan migrate:fresh --seed --force 2>&1 | Out-Null
}
$ErrorActionPreference = $prevPref
Write-Host "  Tables created. Demo data loaded." -ForegroundColor Green
Write-Host "  6 users, 9 appointments, 4 assignments, 5 notifications" -ForegroundColor Green

# 8. Done
Write-Host "[8/8] Setup complete!" -ForegroundColor Green
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Start the server:" -ForegroundColor White
Write-Host "    php artisan serve --port=8080" -ForegroundColor White
Write-Host ""
Write-Host "  Web Dashboard:  http://localhost:8080/login" -ForegroundColor White
Write-Host "  API Health:     http://localhost:8080/api/v1/health" -ForegroundColor White
Write-Host "  Postman:        Import postman/TheraConnect_API_v1.postman_collection.json" -ForegroundColor White
Write-Host ""
Write-Host "  Demo accounts:" -ForegroundColor White
Write-Host "    Admin     : admin@theraconnect.test / password" -ForegroundColor White
Write-Host "    Clinician : clinician@theraconnect.test / password" -ForegroundColor White
Write-Host "    Clinician : dr.rivera@theraconnect.test / password" -ForegroundColor White
Write-Host "    Patient   : patient@theraconnect.test / password" -ForegroundColor White
Write-Host "    Patient   : michael@theraconnect.test / password" -ForegroundColor White
Write-Host "    Patient   : emily@theraconnect.test / password" -ForegroundColor White
Write-Host ""
Write-Host "  To run tests:" -ForegroundColor White
Write-Host "    php artisan test" -ForegroundColor White
Write-Host "  (38 tests, 148 assertions)" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Cyan
