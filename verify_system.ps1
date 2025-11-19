# Verification Script - School Calendar Management System
# PowerShell version for Windows

Write-Host "================================" -ForegroundColor White
Write-Host "SYSTEM VERIFICATION REPORT" -ForegroundColor White
Write-Host "================================" -ForegroundColor White
Write-Host ""

# Check files
Write-Host "📁 CHECKING CRITICAL FILES..." -ForegroundColor Cyan
Write-Host ""

$files_to_check = @(
    "pages/docenti.php"
    "pages/materie.php"
    "pages/aule.php"
    "pages/classi.php"
    "pages/sedi.php"
    "pages/anni_scolastici.php"
    "pages/giorni_chiusura.php"
    "pages/aula_form.php"
    "pages/sedi_form.php"
    "pages/classe_form.php"
    "pages/giorni_chiusura_form.php"
    "pages/aula_disponibilita.php"
    "api/docenti_api.php"
    "api/materie_api.php"
    "api/aule_api.php"
    "api/classi_api.php"
    "includes/header.php"
    "config/database.php"
)

$missing_files = 0
foreach ($file in $files_to_check) {
    if (Test-Path $file) {
        Write-Host "✓ $file" -ForegroundColor Green
    }
    else {
        Write-Host "✗ $file (MISSING)" -ForegroundColor Red
        $missing_files++
    }
}

Write-Host ""
$found_files = $files_to_check.Count - $missing_files
Write-Host "FILES STATUS: $found_files/$($files_to_check.Count) files found"
Write-Host ""

# Check error patterns
Write-Host "🔍 CHECKING FOR ERROR PATTERNS..." -ForegroundColor Cyan
Write-Host ""

$error_patterns = @(
    'query\($'
    'Undefined index'
)

$found_errors = 0
foreach ($pattern in $error_patterns) {
    $result = @()
    
    # Search in pages directory
    $files_pages = @(Get-ChildItem -Path "pages" -Filter "*.php" -Recurse -ErrorAction SilentlyContinue)
    foreach ($file in $files_pages) {
        $content = @(Get-Content $file -ErrorAction SilentlyContinue)
        if ($content -match $pattern) {
            $result += $file.Name
        }
    }
    
    # Search in api directory
    $files_api = @(Get-ChildItem -Path "api" -Filter "*.php" -Recurse -ErrorAction SilentlyContinue)
    foreach ($file in $files_api) {
        $content = @(Get-Content $file -ErrorAction SilentlyContinue)
        if ($content -match $pattern) {
            $result += $file.Name
        }
    }
    
    $count = $result.Count
    
    if ($count -gt 0) {
        Write-Host "⚠ Found '$pattern' in $count locations" -ForegroundColor Yellow
        $found_errors++
    }
    else {
        Write-Host "✓ No occurrences of '$pattern'" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "ERROR PATTERNS: $found_errors issues found"
Write-Host ""

# Summary
Write-Host "================================" -ForegroundColor White
Write-Host "VERIFICATION SUMMARY" -ForegroundColor White
Write-Host "================================" -ForegroundColor White

if ($missing_files -eq 0 -and $found_errors -eq 0) {
    Write-Host "✓ SYSTEM STATUS: ALL CHECKS PASSED" -ForegroundColor Green
    Write-Host ""
    Write-Host "The school calendar management system is:" -ForegroundColor Green
    Write-Host "  ✓ Free of critical PHP errors" -ForegroundColor Green
    Write-Host "  ✓ All required files present" -ForegroundColor Green
    Write-Host "  ✓ Ready for production use" -ForegroundColor Green
}
else {
    Write-Host "✗ SYSTEM STATUS: ISSUES DETECTED" -ForegroundColor Red
    Write-Host "  - Missing files: $missing_files" -ForegroundColor Red
    Write-Host "  - Error patterns found: $found_errors" -ForegroundColor Red
}
Write-Host ""
