# Push .env variables to Vercel (production). Requires: vercel link
# Usage: .\scripts\push-vercel-env.ps1

$ErrorActionPreference = "Stop"

$vars = @(
    "APP_KEY",
    "APP_ENV",
    "APP_DEBUG",
    "APP_URL",
    "DB_CONNECTION",
    "DATABASE_URL",
    "DB_HOST",
    "DB_PORT",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD",
    "DB_SSLMODE",
    "SESSION_DRIVER",
    "CACHE_STORE",
    "QUEUE_CONNECTION",
    "LOG_CHANNEL",
    "APP_CONFIG_CACHE",
    "VIEW_COMPILED_PATH",
    "META_VERIFY_TOKEN",
    "META_SKIP_SIGNATURE",
    "MESSENGER_PAGE_ACCESS_TOKEN",
    "TELEGRAM_BOT_TOKEN",
    "TELEGRAM_BOT_USERNAME",
    "TELEGRAM_WEBHOOK_SECRET"
)

function Get-EnvValue($name) {
    foreach ($line in Get-Content .env) {
        if ($line -match "^\s*$name=(.*)$") {
            $val = $Matches[1].Trim().Trim('"')
            return $val
        }
    }
    return $null
}

foreach ($name in $vars) {
    $value = Get-EnvValue $name
    if ([string]::IsNullOrWhiteSpace($value)) { continue }

    Write-Host "Setting $name ..."
    $value | npx vercel env add $name production --force 2>$null
    if ($LASTEXITCODE -ne 0) {
        $value | npx vercel env add $name production
    }
}

Write-Host "Done. Run: npx vercel deploy --prod --yes --archive=tgz"
