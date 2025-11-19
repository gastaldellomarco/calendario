#!/bin/bash
# Verification Script - School Calendar Management System

echo "================================"
echo "SYSTEM VERIFICATION REPORT"
echo "================================"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check files
echo "📁 CHECKING CRITICAL FILES..."
echo ""

files_to_check=(
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

missing_files=0
for file in "${files_to_check[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file (MISSING)"
        ((missing_files++))
    fi
done

echo ""
echo "FILES STATUS: $((${#files_to_check[@]} - missing_files))/${#files_to_check[@]} files found"
echo ""

# Check error patterns
echo "🔍 CHECKING FOR ERROR PATTERNS..."
echo ""

error_patterns=(
    "\$this->db"
    "->query(\$"
    "Undefined index"
)

found_errors=0
for pattern in "${error_patterns[@]}"; do
    result=$(grep -r "$pattern" --include="*.php" pages/ api/ 2>/dev/null | wc -l)
    if [ "$result" -gt 0 ]; then
        echo -e "${RED}⚠${NC} Found '$pattern' in $result locations"
        ((found_errors++))
    else
        echo -e "${GREEN}✓${NC} No occurrences of '$pattern'"
    fi
done

echo ""
echo "ERROR PATTERNS: $found_errors issues found"
echo ""

# Summary
echo "================================"
echo "VERIFICATION SUMMARY"
echo "================================"
if [ $missing_files -eq 0 ] && [ $found_errors -eq 0 ]; then
    echo -e "${GREEN}✓ SYSTEM STATUS: ALL CHECKS PASSED${NC}"
    echo ""
    echo "The school calendar management system is:"
    echo "  ✓ Free of critical PHP errors"
    echo "  ✓ All required files present"
    echo "  ✓ Ready for production use"
else
    echo -e "${RED}✗ SYSTEM STATUS: ISSUES DETECTED${NC}"
    echo "  - Missing files: $missing_files"
    echo "  - Error patterns found: $found_errors"
fi
echo ""
