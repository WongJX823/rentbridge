<#
.SYNOPSIS
    Dumps dbrb_2026 to a timestamped .sql file, archives uploads/ to a
    timestamped .zip, and prunes old backups of both.

.DESCRIPTION
    RentBridge's recover-lost-data safety net (see TODO.md "Data protection").
    Covers BOTH halves of the app's persisted state: the database (contract
    rows, users, etc.) AND uploads/ (the actual signed contract PDFs,
    signature images, and property documents the DB rows point to). The DB
    alone is not enough — contracts.contract_pdf_path etc. are just paths;
    losing uploads/ with no backup means those files are gone forever even
    though the DB rows still look fine.

    Run manually, or register as a daily Windows Task Scheduler job:

        schtasks /Create /TN "RentBridge DB Backup" /SC DAILY /ST 02:00 ^
            /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File D:\Xampp\htdocs\rentbridge\backups\backup_db.ps1" ^
            /RU SYSTEM

    Or via the Task Scheduler GUI: Create Task > Trigger: Daily > Action:
    Start a program > Program: powershell.exe > Arguments:
        -NoProfile -ExecutionPolicy Bypass -File "D:\Xampp\htdocs\rentbridge\backups\backup_db.ps1"

    For point-in-time recovery beyond daily snapshots, also enable the MySQL
    binary log (log_bin in my.ini) — not done here since it changes server
    config rather than app-level state. For real disaster protection (server
    disk loss, not just "oops deleted a file"), copy this script's output
    off-box (cloud storage, another machine) — a backup that lives on the
    same disk as what it's backing up doesn't survive a disk failure.

.NOTES
    Dumps/archives are written next to this script and are gitignored
    (backups/*.sql, backups/*.zip). Keeps the last $RetentionDays days of
    each; older ones are deleted. uploads/mpdf_tmp is excluded from the
    archive — it's mPDF's own scratch space (font caches etc.), not user data.
#>

param(
    [string]$DbName        = 'dbrb_2026',
    [string]$MysqldumpPath = 'D:\Xampp\mysql\bin\mysqldump.exe',
    [string]$MysqlUser     = 'root',
    [string]$BackupDir     = $PSScriptRoot,
    [string]$UploadsDir    = (Join-Path $PSScriptRoot '..\uploads'),
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

$timestamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'

# --- 1. Database dump ---
$dbOutputFile = Join-Path $BackupDir "${DbName}_${timestamp}.sql"

Write-Host "Dumping $DbName to $dbOutputFile ..."
& $MysqldumpPath -u $MysqlUser --routines --triggers --single-transaction $DbName |
    Out-File -FilePath $dbOutputFile -Encoding utf8

if ($LASTEXITCODE -ne 0) {
    Write-Error "mysqldump exited with code $LASTEXITCODE"
    if (Test-Path $dbOutputFile) { Remove-Item $dbOutputFile -Force }
    exit $LASTEXITCODE
}

$dbSize = (Get-Item $dbOutputFile).Length
if ($dbSize -eq 0) {
    Write-Error "Database backup file is empty — treating as a failed dump."
    Remove-Item $dbOutputFile -Force
    exit 1
}

Write-Host ("DB backup OK: {0} ({1:N1} KB)" -f $dbOutputFile, ($dbSize / 1KB))

# --- 2. uploads/ archive (signed contracts, signatures, property docs) ---
if (-not (Test-Path $UploadsDir)) {
    Write-Warning "Uploads directory not found at '$UploadsDir' — skipping uploads backup."
} else {
    $uploadsOutputFile = Join-Path $BackupDir "uploads_${timestamp}.zip"

    $foldersToBackup = Get-ChildItem -Path $UploadsDir -Directory |
        Where-Object { $_.Name -ne 'mpdf_tmp' }

    if ($foldersToBackup.Count -eq 0) {
        Write-Warning "No uploads subfolders to back up — skipping uploads archive."
    } else {
        Write-Host "Archiving uploads/ to $uploadsOutputFile ..."
        Compress-Archive -Path $foldersToBackup.FullName -DestinationPath $uploadsOutputFile -Force

        $uploadsSize = (Get-Item $uploadsOutputFile).Length
        if ($uploadsSize -eq 0) {
            Write-Error "Uploads archive is empty — treating as a failed backup."
            Remove-Item $uploadsOutputFile -Force
            exit 1
        }

        Write-Host ("Uploads backup OK: {0} ({1:N1} MB)" -f $uploadsOutputFile, ($uploadsSize / 1MB))
    }
}

# --- Prune backups (both kinds) older than the retention window ---
$cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem -Path $BackupDir -Filter "${DbName}_*.sql" |
    Where-Object { $_.LastWriteTime -lt $cutoff } |
    ForEach-Object {
        Write-Host "Pruning old backup: $($_.Name)"
        Remove-Item $_.FullName -Force
    }
Get-ChildItem -Path $BackupDir -Filter "uploads_*.zip" |
    Where-Object { $_.LastWriteTime -lt $cutoff } |
    ForEach-Object {
        Write-Host "Pruning old backup: $($_.Name)"
        Remove-Item $_.FullName -Force
    }
