#!/usr/bin/env python3
"""Regression for the exact HY093 placeholder mismatch in Office-test migrations."""
from pathlib import Path

source = (Path(__file__).resolve().parents[1] / "deployment/office_test_migrate.php").read_text()
required = [
    "$rejectionChecksumLookup=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');",
    "$rejectionChecksumLookup->execute([$rejectionMigrationId]);",
    "$previousChecksum=$rejectionChecksumLookup->fetchColumn();",
    "$q->execute([$rejectionMigrationId,$rejectionChecksum]);",
]
for marker in required:
    assert marker in source, "Missing safe migration checksum lookup or insertion: " + marker
assert "$q->execute([$rejectionMigrationId]);" not in source, (
    "OnSite checksum lookup reused two-placeholder insert, causing SQLSTATE HY093"
)
print("OFFICE_TEST_MIGRATION_PLACEHOLDERS=PASS")
