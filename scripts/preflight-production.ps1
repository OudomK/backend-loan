$ErrorActionPreference = "Stop"

if (-not (Test-Path ".env")) {
    Write-Host ".env not found." -ForegroundColor Red
    exit 1
}

$required = @(
    "APP_ENV",
    "APP_DEBUG",
    "APP_KEY",
    "APP_URL",
    "DB_CONNECTION",
    "DB_HOST",
    "DB_PORT",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD"
)

$envMap = @{}
Get-Content .env | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#")) {
        return
    }

    $idx = $line.IndexOf("=")
    if ($idx -lt 1) {
        return
    }

    $key = $line.Substring(0, $idx).Trim()
    $value = $line.Substring($idx + 1).Trim().Trim('"')
    $envMap[$key] = $value
}

function Get-EnvValue([string]$key) {
    if ($envMap.ContainsKey($key)) {
        return [string]$envMap[$key]
    }

    return ""
}

$errors = @()
$warnings = @()

foreach ($key in $required) {
    if (-not $envMap.ContainsKey($key) -or [string]::IsNullOrWhiteSpace($envMap[$key])) {
        $errors += "$key is missing or empty"
    }
}

if ((Get-EnvValue "APP_ENV") -ne "production") {
    $errors += "APP_ENV must be production"
}

if ((Get-EnvValue "APP_DEBUG").ToLower() -ne "false") {
    $errors += "APP_DEBUG must be false"
}

if ((Get-EnvValue "APP_URL").Contains("localhost") -or (Get-EnvValue "APP_URL").Contains("127.0.0.1")) {
    $warnings += "APP_URL still points to localhost/127.0.0.1"
}

if ((Get-EnvValue "DB_USERNAME").ToLower() -eq "root") {
    $warnings += "DB_USERNAME is root (use a dedicated least-privileged DB user in production)"
}

if ((Get-EnvValue "MAIL_MAILER").ToLower() -eq "log") {
    $warnings += "MAIL_MAILER is log (emails will not be sent)"
}

if ($errors.Count -gt 0) {
    Write-Host "Preflight FAILED:" -ForegroundColor Red
    $errors | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
}

if ($warnings.Count -gt 0) {
    Write-Host "Preflight warnings:" -ForegroundColor Yellow
    $warnings | ForEach-Object { Write-Host " - $_" -ForegroundColor Yellow }
}

if ($errors.Count -eq 0 -and $warnings.Count -eq 0) {
    Write-Host "Preflight passed. Environment looks production-ready." -ForegroundColor Green
}

if ($errors.Count -gt 0) {
    exit 1
}
