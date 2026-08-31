# RentBridge — run all automated test suites.
# Usage:  .\run-tests.ps1  [--unit-only|--e2e-only|--skip-unit|--skip-e2e|--skip-smoke]
# Requires PHP on PATH (XAMPP: add D:\Xampp\php to PATH, or edit $php below).

param([Parameter(ValueFromRemainingArguments = $true)] $Args)

$php = "php"
if (-not (Get-Command $php -ErrorAction SilentlyContinue)) {
    foreach ($cand in @("D:\Xampp\php\php.exe", "C:\xampp\php\php.exe")) {
        if (Test-Path $cand) { $php = $cand; break }
    }
}

& $php (Join-Path $PSScriptRoot "tests\run_all.php") @Args
exit $LASTEXITCODE
