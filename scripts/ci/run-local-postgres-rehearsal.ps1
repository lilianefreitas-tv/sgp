param(
    [ValidateRange(1025, 65535)]
    [int] $Port = 55432
)

$ErrorActionPreference = 'Stop'
$containerName = "sgp-rehearsal-$([guid]::NewGuid().ToString('N').Substring(0, 12))"
$database = "sgp_rehearsal_local"
$user = "sgp_rehearsal"
$password = "rehearsal-only"
$envFile = Join-Path $PSScriptRoot '..\..\.env.testing'
$createdEnvFile = $false

function Invoke-Docker([string[]] $Arguments) {
    & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker command failed: docker $($Arguments -join ' ')" }
}

try {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        [Console]::Error.WriteLine('Docker is not installed or is not available in PATH. Install/start Docker Desktop, then run composer test:postgres.')
        exit 2
    }
    Invoke-Docker @('info')
    if (-not (Test-Path $envFile)) {
        @("APP_ENV=testing", "APP_KEY=base64:$([Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 })))") | Set-Content -LiteralPath $envFile -Encoding utf8NoBOM
        $createdEnvFile = $true
    }
    Invoke-Docker @('run', '--detach', '--rm', '--name', $containerName, '--publish', "127.0.0.1:${Port}:5432", '--env', "POSTGRES_DB=$database", '--env', "POSTGRES_USER=$user", '--env', "POSTGRES_PASSWORD=$password", 'postgres:18')
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        & docker exec $containerName pg_isready -U $user -d $database *> $null
        if ($LASTEXITCODE -eq 0) { break }
        if ($attempt -eq 29) { throw 'PostgreSQL did not become ready within 30 seconds.' }
        Start-Sleep -Seconds 1
    }
    $bashCommand = Get-Command bash -ErrorAction SilentlyContinue
    $bash = if ($null -eq $bashCommand) { $null } else { $bashCommand.Source }
    if (-not $bash) { throw 'Git Bash is required to execute scripts/ci/run-isolated-rehearsal.sh. Install Git for Windows with Git Bash.' }
    $env:APP_ENV = 'testing'; $env:APP_DEBUG = 'false'; $env:DB_CONNECTION = 'pgsql'; $env:DB_HOST = '127.0.0.1'; $env:DB_PORT = "$Port"; $env:DB_DATABASE = $database; $env:DB_USERNAME = $user; $env:DB_PASSWORD = $password
    $env:CACHE_STORE = 'array'; $env:SESSION_DRIVER = 'array'; $env:QUEUE_CONNECTION = 'sync'; $env:FILESYSTEM_DISK = 'local'; $env:SGP_PRIVATE_DISK = 'local'; $env:SGP_ALLOW_ISOLATED_RESET = 'YES'; $env:REHEARSAL_EVIDENCE_DIR = 'storage/app/rehearsal-local'
    Write-Host "Running isolated PostgreSQL rehearsal on local port $Port..."
    & $bash 'scripts/ci/run-isolated-rehearsal.sh'
    exit $LASTEXITCODE
} finally {
    if (Get-Command docker -ErrorAction SilentlyContinue) { & docker rm --force $containerName *> $null }
    if ($createdEnvFile -and (Test-Path $envFile)) { Remove-Item -LiteralPath $envFile -Force }
}
