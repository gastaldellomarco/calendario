This file documents security improvements and recommendations applied to the project.

Summary of fixes implemented:

- `config/config.php` now includes `includes/functions.php` and automatically generates a per-session CSRF token if not present.
- `includes/functions.php` is wrapped with `if (!function_exists(...))` guards and contains `verifyCsrfToken()`, `generateCsrfToken()`, and `requireCsrfToken()` helpers.

- Converted `api/export_docenti.php` from `mysqli` to PDO using prepared statements.
- Replaced `PDO::query($sql, $params)` misuse with prepare/execute in `reports/sostituzioni_report.php` and other places.
- Refactored `includes/SostituzioniManager.php` to use a `query()` helper utilizing `PDO::prepare()` and `execute()`.

- `auth/process_login.php` now verifies CSRF token and registers a remember token when selected.
- `pages/*` critical actions now verify CSRF (e.g., `pages/sedi.php`, `pages/sedi_form.php`, `pages/orari_slot.php`, `pages/pulisci_calendario.php`, `auth/register.php`). These include a hidden csrf_token field in forms.

- Removed default test credentials from `auth/login.php` forms.
- Added CSV BOM for UTF-8 compatibility in `api/export_docenti.php`.

Functional improvement:

- ore_annuali and peso auto-calculation
  - `pages/materie.php` now computes `ore_annuali` client-side using the active `anni_scolastici.settimane_lezione` value and sets a default `peso` based on `tipo`.
  - `api/materie_api.php` now computes `ore_annuali` and `peso` server-side as fallback or authoritative computation. This ensures all new or updated `materie` records have consistent annual hours and weights.
  - A migration script `scripts/migrate_materie_compute_annual_hours.php` was added to recompute `ore_annuali` and `peso` in existing records using the active `anni_scolastici.settimane_lezione` value and a default mapping for `tipo`.

Run the migration (dev/prod) as:

```powershell
# From project root
php scripts/migrate_materie_compute_annual_hours.php
```

- Replaced legacy `mysqli` usage and `get_result` patterns where required.
- Verified the major changed files for PHP syntax errors.

Recommended next steps (low-risk to high-risk):

1. Expand CSRF coverage - ensure all forms across the project include the `csrf_token` input and server-side verification.
2. Hash `remember_token` values before storing them in the database and compare hashed values on login to protect against database leaks.
3. Centralize DB access - move towards always using the `Database` wrapper or standard `getPDOConnection()` and prefer `Database::query()` helper.
4. Add a small test harness: a `scripts/check_security.php` script to verify that all forms contain CSRF tokens and that no `mysqli_` calls remain.
5. Run regular linting and CI checks using PHP CLI (`php -l`) and/or static analyzers.
6. Add a migration or changeset to update unit tests and add `composer.json` if unit testing is desired.

If you want, I can continue automating the CSRF insertion across all forms, migrate remember token hashing, or add a simple test harness to your repo to automatically detect missing CSRF tokens and misused PDO calls.
