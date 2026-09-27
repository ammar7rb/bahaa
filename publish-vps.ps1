param(
    [string]$Message = "Deploy site update $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

$expectedRemote = 'sigma-eg@187.77.66.167:/home/sigma-eg/git/sigma-site.git'
$remote = (& git remote get-url vps).Trim()
if ($LASTEXITCODE -ne 0 -or $remote -ne $expectedRemote) {
    throw 'The vps Git remote is missing or points to a different server.'
}

& git add -A
if ($LASTEXITCODE -ne 0) { throw 'Could not stage the site files.' }

$staged = @(& git diff --cached --name-only)
$blocked = @($staged | Where-Object {
    $_ -match '(^|/)(\.env($|\.)|oauth-(private|public)\.key$|.*\.(pem|p12|sql|log)$|\.codex-backups|\.phpunit\.cache)'
})
if ($blocked.Count -gt 0) {
    throw "Sensitive or local-only files were staged: $($blocked -join ', ')"
}

$php = Join-Path $PSScriptRoot '..\..\php\php.exe'
if (-not (Test-Path -LiteralPath $php)) { throw "PHP was not found at $php" }
foreach ($file in @($staged | Where-Object { $_ -like '*.php' -and (Test-Path -LiteralPath $_) })) {
    & $php -l -- $file | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax error: $file" }
}

if ($staged.Count -gt 0) {
    & git commit -m $Message
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the deployment commit.' }
}

$revision = (& git rev-parse HEAD).Trim()
& git push vps HEAD:refs/heads/main
if ($LASTEXITCODE -ne 0) { throw 'Git transfer to the VPS failed.' }

$deployed = (& ssh -o BatchMode=yes sigma-eg@187.77.66.167 'cat /home/sigma-eg/htdocs/sigma-eg.net/.deploy-current').Trim()
if ($LASTEXITCODE -ne 0 -or $deployed -ne $revision) {
    throw 'The VPS received the commit but the deployment did not finish. Check the server deployment log.'
}

$status = & curl.exe --silent --show-error --output NUL --write-out '%{http_code}' 'https://sigma-eg.net/api/v1/config'
if ($LASTEXITCODE -ne 0 -or $status -ne '200') {
    throw "Deployment finished, but the public API returned HTTP $status."
}

Write-Host "Published $revision to the VPS; API returned HTTP 200."
