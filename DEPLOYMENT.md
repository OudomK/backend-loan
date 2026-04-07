# Production Deployment Guide

## 1) Prepare production environment file
1. Copy `.env.production.example` to `.env`.
2. Fill all real values:
   - `APP_KEY` (generate using `php artisan key:generate --show`)
   - `APP_URL` (real public URL)
   - `DB_*` credentials
   - `MAIL_*` credentials
3. Ensure these are set for production:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `LOG_LEVEL=warning`

## 2) First-time server setup
1. Install PHP extensions required by Laravel + your DB driver.
2. Install Composer and Node.js.
3. Create writable permissions for:
   - `storage/`
   - `bootstrap/cache/`
4. Create storage link:
   - `php artisan storage:link`

## 3) Run deploy script

### PowerShell (Windows)
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-production.ps1
```

### Bash (Linux)
```bash
chmod +x ./scripts/deploy-production.sh
./scripts/deploy-production.sh
```

Before deploy (recommended):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\preflight-production.ps1
```

Optional flags:
- Skip frontend build:
  - PowerShell: `-SkipNpm`
  - Bash: `SKIP_NPM=1 ./scripts/deploy-production.sh`
- Skip migration:
  - PowerShell: `-SkipMigrate`
  - Bash: `SKIP_MIGRATE=1 ./scripts/deploy-production.sh`

Alternative via Composer:
```bash
composer run deploy:prod
```

## 4) Post-deploy checks
1. `php artisan about` should show:
   - `Environment: production`
   - `Debug Mode: DISABLED`
   - `Config: CACHED`
   - `Routes: CACHED`
2. Visit:
   - `/admin/login`
   - key pages: loans, repayment-transactions, manage-settings
3. Verify file upload preview works and URLs are served from `/storage/...`.
4. Start queue worker (required if `QUEUE_CONNECTION=database`):
```bash
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

## 5) Common issues
- `view:cache` fails with "Access is denied" on Windows:
  - stop running `php artisan serve` and queue workers
  - clear again with `php artisan optimize:clear`
  - rerun deploy script
- Avatar/image CORS between `localhost` and `127.0.0.1`:
  - use one host consistently
  - keep `APP_PUBLIC_STORAGE_URL=/storage`
