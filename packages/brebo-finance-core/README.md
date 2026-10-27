# BREBO Finance core

Framework-independent extraction of the existing payables work queue, invoice control view and performance blocker resolver. Business rules were moved unchanged from `web/modules/custom/brebo_finance/src/Service`; the old names are compatibility aliases to this single implementation. The four contracts are aliases as well, so existing database adapters keep their type identity.

Load `bootstrap.php` in a plain PHP runtime; no Composer installation, CMS bootstrap or provider SDK is required. Office supplies read-only PDO and Office identity/project adapters. Existing payment, approval, matching, bank and reconciliation managers remain outside this extraction and remain authoritative. Queue membership is a display classification, never authorization to pay.

The fields `project_nid` and `actorUid` remain contract vocabulary for compatibility, not runtime dependencies. Office explicitly maps source project IDs to its own project IDs; equal numbers are not identity proof.

Run from repository root:

```
php office-independent/tests/OfficeFinanceRegressionTest.php
php office-independent/tests/OfficeFinanceHttpTest.php
```

Any future rule change must be tested against both Office and the legacy aliases. Deploy this package with the legacy module as well as with the standalone Office runtime.
