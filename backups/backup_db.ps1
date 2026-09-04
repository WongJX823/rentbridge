<#
.SYNOPSIS
    Dumps dbrb_2026 to a timestamped .sql file and prunes old dumps.

.DESCRIPTION
    RentBridge's recover-lost-data safety net (see TODO.md "Data protection").
    Run manually, or register as a daily Windows Task Scheduler job:

        schtasks /Create /TN "RentBridge DB Backup" /SC DAILY /ST 02:00 ^
            /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File D:\Xampp\htdocs\rentbridge\backups\backup_db.ps1" ^
            /RU SYSTEM

    Or via the Task Scheduler GUI: Create Task > Trigger: Daily > Action:
    Start a program > Program: powershell.exe > Arguments:
        -NoProfile -ExecutionPolicy Bypass -File "D:\Xampp\htdocs\rentbridge\backups\backup_db.ps1"

    For point-in-time recovery beyond daily snapshots, also enable the MySQL
    binary log (log_bin in my.ini) — not done here since it changes server
    config rather than app-level state.

.NOTES
    Dumps are written next to this script and are gitignored (backups/*.sql).
    Keeps the last $RetentionDays days of dumps; older ones are deleted.
#>

param(
    [string]$DbName        = 'dbrb_2026',
    [string]$MysqldumpPath = 'D:\Xampp\mysql\bin\mysqldump.exe',
    [string]$MysqlUser     = 'root',
    [string]$BackupDir     = $PSScriptRoot,
    [int]   $RetentionDays = 30
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $MysqldumpPath)) {
    Write-Error "mysqldump not found at '$MysqldumpPath'. Pass -MysqldumpPath to override."
    exit 1
}

if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
}

$timestamp  = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$outputFile = Join-Path $BackupDir "${DbName}_${timestamp}.sql"

Write-Host "Dumping $DbName to $outputFile ..."
& $MysqldumpPath -u $MysqlUser --routines --triggers --single-transaction $DbName |
    Out-File -FilePath $outputFile -Encoding utf8

if ($LASTEXITCODE -ne 0) {
    Write-Error "mysqldump exited with code $LASTEXITCODE"
    if (Test-Path $outputFile) { Remove-Item $outputFile -Force }
    exit $LASTEXITCODE
}

$size = (Get-Item $outputFile).Length
if ($size -eq 0) {
    Write-Error "Backup file is empty — treating as a failed dump."
    Remove-Item $outputFile -Force
    exit 1
}

Write-Host ("Backup OK: {0} ({1:N1} KB)" -f $outputFile, ($size / 1KB))

# Prune dumps older than the retention window.
$cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem -Path $BackupDir -Filter "${DbName}_*.sql" |
    Where-Object { $_.LastWriteTime -lt $cutoff } |
    ForEach-Object {
        Write-Host "Pruning old backup: $($_.Name)"
        Remove-Item $_.FullName -Force
    }
