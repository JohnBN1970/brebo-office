#!/usr/bin/env python3
"""Contract tests for guarded V3 project tab deployment against a synthetic test release."""
import hashlib
import os
import pathlib
import subprocess
import sys
import tempfile
import unittest

BASE = pathlib.Path(__file__).resolve().parents[1]
SCRIPT = BASE / "deployment/hostinger/mount-work-budget-tab-v3.py"
REPO_PROJECT = BASE / "public/office-project.php"

LIVE_PAGE = r"""<?php
// OfficeNativeProjectAccess($db))->canRead authorization exists before this output.
// Financial dossier: explicit Finance ACL, independent of project read access.
$financeContracts = [];
$financeHtml = '';
$moduleHtml = '';
$tabs = ['overzicht' => 'Overzicht', 'uitvoering' => 'Uitvoering', 'financieel' => 'Financieel'];
$dashboardTab = 'overzicht';
// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only.
if (\Brebo\Office\Intake\OfficeMount::isTest()
    && getenv('OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED') === 'enabled'
    && is_array($financeContracts)) {
    try {
        $workBudgetView = null;
        $financeHtml .= \Brebo\Office\WorkingBudget\ProjectBudgetPanel::render($workBudgetView);
    } catch (\Throwable $workBudgetError) {
        $financeHtml .= '<p>Werkbegroting tijdelijk niet beschikbaar.</p>';
    }
}
$moduleHtml .= $financeHtml;
// Existing V3 independent rendering contract.
$projectV3Sections = [
    'overzicht' => '',
    'werkvoorbereiding' => (string) $moduleHtml,
    'financieel' => (string) $financeHtml,
];
$projectV3Body = \Brebo\Office\Project\OfficeProjectDetailV3View::render(
    '/office-test', 1, '', '', null, 0, $dashboardTab, $projectV3Sections
);
"""

LIVE_STRUCTURE = r"""<?php
final class OfficeProjectPageStructure {
    public static function detailTabs(): array {
        return [
            'overzicht' => 'Overzicht',
            'uitvoering' => 'Uitvoering',
            'financieel' => 'Financieel',
        ];
    }
}
"""


class V3TabDeployTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        root = pathlib.Path(self.tmp.name) / "office_calc_test/release-fixture/private"
        self.project = root / "public/office-project.php"
        self.structure = root / "src/Project/OfficeProjectPageStructure.php"
        self.project.parent.mkdir(parents=True)
        self.structure.parent.mkdir(parents=True)
        self.project.write_text(LIVE_PAGE)
        self.structure.write_text(LIVE_STRUCTURE)
        self.project_before = self.project.read_bytes()
        self.structure_before = self.structure.read_bytes()

    def run_mount(self):
        env = dict(os.environ)
        env.update({
            "OFFICE_BASE_PATH": "/office-test",
            "OFFICE_WORK_BUDGET_TAB_EXPECTED_SHA256": hashlib.sha256(self.project.read_bytes()).hexdigest(),
            "OFFICE_WORK_BUDGET_STRUCTURE_EXPECTED_SHA256": hashlib.sha256(self.structure.read_bytes()).hexdigest(),
        })
        return subprocess.run([sys.executable, str(SCRIPT), str(self.project), str(REPO_PROJECT), str(self.structure)],
                              env=env, check=False, capture_output=True, text=True)

    def test_surgical_mount_preserves_original_finance_and_is_idempotent(self):
        result = self.run_mount()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        src = self.project.read_text()
        cls = self.structure.read_text()
        self.assertIn("'werkbegroting' => (string) $workBudgetTabHtml,", src)
        self.assertIn("'werkbegroting' => 'Werkbegroting'", src)
        self.assertIn("'werkbegroting' => 'Werkbegroting'", cls)
        self.assertIn("$moduleHtml .= $financeHtml;", src)
        self.assertIn("is_array($financeContracts)", src)
        self.assertNotIn("$financeHtml .= \\Brebo\\Office\\WorkingBudget\\ProjectBudgetPanel::render", src)
        self.assertEqual(self.project.with_name("office-project.php.pre-work-budget-tab-v3.bak").read_bytes(),
                         self.project_before)
        self.assertEqual(self.structure.with_name("OfficeProjectPageStructure.php.pre-work-budget-tab-v3.bak").read_bytes(),
                         self.structure_before)
        second = self.run_mount()
        self.assertEqual(second.returncode, 0, second.stdout + second.stderr)
        self.assertIn("ALREADY_INSTALLED", second.stdout)

    def test_absent_v3_map_aborts_without_writes(self):
        self.project.write_text(LIVE_PAGE.replace("'financieel' => (string) $financeHtml,", ""))
        page = self.project.read_bytes()
        result = self.run_mount()
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.project.read_bytes(), page)
        self.assertEqual(self.structure.read_bytes(), self.structure_before)
        self.assertFalse(self.project.with_name("office-project.php.pre-work-budget-tab-v3.bak").exists())

    def test_missing_finance_acl_aborts_without_writes(self):
        self.project.write_text(LIVE_PAGE.replace("is_array($financeContracts)", "true"))
        page = self.project.read_bytes()
        result = self.run_mount()
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.project.read_bytes(), page)
        self.assertEqual(self.structure.read_bytes(), self.structure_before)


if __name__ == "__main__":
    unittest.main()
