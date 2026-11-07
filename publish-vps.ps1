param(
    [Parameter(Mandatory=$true)][string[]]$Paths,
    [Parameter(Mandatory=$true)][ValidatePattern('^[a-f0-9]{40}$')][string]$ExpectedBase,
    [string]$Message = "Publish Sigma site update"
)
$ErrorActionPreference='Stop'
Set-Location -LiteralPath $PSScriptRoot
$expectedRemote='sigma-eg@187.77.66.167:/home/sigma-eg/git/sigma-site.git'
if ((& git remote get-url vps).Trim() -ne $expectedRemote) {throw 'Unexpected vps remote.'}
$sshKey=Join-Path $env:USERPROFILE '.ssh/sigma_site_git_push'
if (!(Test-Path -LiteralPath $sshKey)) {throw 'Deployment SSH key is missing.'}
$env:GIT_SSH_COMMAND="ssh -i `"$sshKey`" -o IdentitiesOnly=yes -o BatchMode=yes"
& git fetch vps main
if ($LASTEXITCODE -ne 0) {throw 'Could not fetch current repository main.'}
$parent=(& git rev-parse FETCH_HEAD).Trim()
if ($parent -ne $ExpectedBase) {throw 'Repository main changed; review the latest source before publishing.'}
$index=Join-Path ([IO.Path]::GetTempPath()) ('sigma-index-'+[guid]::NewGuid())
$body=$index+'.txt';$previousIndex=$env:GIT_INDEX_FILE
try {
    $env:GIT_INDEX_FILE=$index
    & git read-tree $parent
    if ($LASTEXITCODE -ne 0) {throw 'Could not initialize isolated release index.'}
    foreach ($file in $Paths) {
        $file=$file.Replace('\','/')
        if ($file.StartsWith('/') -or $file.Contains('..') -or $file.Contains(':') -or $file -match '(^|/)(\.env($|\.)|.*\.(key|pem|p12|pfx|sql|log|sqlite)$)|^(storage/|vendor/|node_modules/|bootstrap/cache/|public/storage/|\.well-known/|\.deploy-)') {throw "Protected path: $file"}
        if (Test-Path -LiteralPath $file -PathType Container) {throw 'List explicit files, not directories.'}
        & git --literal-pathspecs add -- $file
        if ($LASTEXITCODE -ne 0) {throw "Could not stage $file"}
        if (Test-Path -LiteralPath $file -PathType Leaf) {
            if ($file -like '*.php') {
                $php='E:/xampp/php/php.exe';if (!(Test-Path -LiteralPath $php)) {$php='php'}
                & $php -l -- $file | Out-Null
                if ($LASTEXITCODE -ne 0) {throw "PHP syntax error: $file"}
            }
            if ($file -match '\.(php|json|js|yml|yaml|sh|ps1|md)$' -and (Select-String -LiteralPath $file -Pattern '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----' -Quiet)) {throw "Private key material in $file"}
        }
    }
    & git diff --cached --check
    if ($LASTEXITCODE -ne 0) {throw 'Release diff check failed.'}
    $tree=(& git write-tree).Trim()
    $parentTree=(& git rev-parse "$parent`^{tree}").Trim()
    if ($tree -eq $parentTree) {throw 'No source changes selected.'}
    [IO.File]::WriteAllText($body,$Message,[Text.UTF8Encoding]::new($false))
    $revision=(& git commit-tree $tree -p $parent -F $body).Trim()
    if ($LASTEXITCODE -ne 0) {throw 'Could not create release commit.'}
    & git update-ref "refs/heads/releases/sigma-$($revision.Substring(0,12))" $revision
    & git push vps "${revision}:refs/heads/main"
    if ($LASTEXITCODE -ne 0) {throw 'Repository push failed.'}
    $deployed=(& ssh -i $sshKey -o IdentitiesOnly=yes -o BatchMode=yes sigma-eg@187.77.66.167 'cat /home/sigma-eg/htdocs/sigma-eg.net/.deploy-current').Trim()
    if ($LASTEXITCODE -ne 0 -or $deployed -ne $revision) {throw 'Commit received but deployment is not confirmed; inspect the server deployment log.'}
    $status=& curl.exe --silent --show-error --output NUL --write-out '%{http_code}' https://sigma-eg.net/api/v1/config
    if ($LASTEXITCODE -ne 0 -or $status -ne '200') {throw 'Public API health check failed.'}
    Write-Host "Repository main and production are synchronized at $revision; API HTTP 200."
} finally {
    $env:GIT_INDEX_FILE=$previousIndex
    Remove-Item -LiteralPath $index,$body -Force -ErrorAction SilentlyContinue
}
