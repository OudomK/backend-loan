param(
    [switch]$SkipNpm,
    [switch]$SkipMigrate
)

$ErrorActionPreference = "Stop"

function Run-Step {
    param(
        [string]$Name,
        [string]$Command
    )

    Write-Host "==> $Name" -ForegroundColor Cyan
    Invoke-Expression $Command
}

Write-Host "Starting production deploy..." -ForegroundColor Green

if (-not (Test-Path ".env")) {
    throw ".env not found. Copy from .env.production.example and fill real values first."
}

$appIsDown = $false

try {
    Run-Step "Composer install (no-dev, optimized)" "composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist"

    if (-not $SkipNpm) {
        Run-Step "NPM install" "npm ci"
        Run-Step "Build frontend assets" "npm run build"
    }

    Run-Step "Put app in maintenance mode" "php artisan down"
    $appIsDown = $true

    if (-not $SkipMigrate) {
        Run-Step "Run migrations (force)" "php artisan migrate --force"
    }

    Run-Step "Ensure storage symlink" "php artisan storage:link"
    Run-Step "Clear old caches" "php artisan optimize:clear"
    Run-Step "Cache config" "php artisan config:cache"
    Run-Step "Cache routes" "php artisan route:cache"
    Run-Step "Cache events" "php artisan event:cache"
    Run-Step "Clear compiled views" "php artisan view:clear"
    Run-Step "Cache views" "php artisan view:cache"
    Run-Step "Bring app up" "php artisan up"
    $appIsDown = $false
}
catch {
    if ($appIsDown) {
        try {
            Run-Step "Recover app state (up)" "php artisan up"
        }
        catch {
        }
    }

    throw
}

Write-Host "Deploy completed successfully." -ForegroundColor Green
