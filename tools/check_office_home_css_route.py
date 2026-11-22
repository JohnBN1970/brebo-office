#!/usr/bin/env python3
"""Static safety contract for the opt-in Office-test stylesheet route."""
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
page = (root / "office-independent/public/office-home.php").read_text(encoding="utf-8")
router = (root / "office-independent/deployment/hostinger/office-test-index.php").read_text(encoding="utf-8")
required = {
    "test-only flag": "getenv('OFFICE_TEST_HOME_EXTERNAL_CSS_ENABLED') === 'enabled'",
    "test environment": "OfficeMount::isTest()",
    "authenticated CSS request": "if (isset($_GET['office_css']))",
    "stylesheet file": "__DIR__ . '/assets/css/office-home.css'",
    "CSS MIME": "Content-Type: text/css; charset=utf-8",
    "no-store response": "Cache-Control: no-store, private",
    "same-origin stylesheet CSP": "style-src 'self' 'unsafe-inline'",
    "test-only stylesheet URL": '/office-test/?office_css=home',
}
missing = [label for label, needle in required.items() if needle not in page]
if "'/office-test/' => 'office-home.php'" not in router:
    missing.append("test router mapping")
if missing:
    sys.exit("FAIL Office home CSS contract: " + ", ".join(missing))
print("PASS: Office home CSS route and security contract")
