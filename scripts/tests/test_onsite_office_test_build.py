import importlib.util
from pathlib import Path
import plistlib
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('onsite_test_config', ROOT / 'scripts/configure-onsite-office-test-ios.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class OfficeTestBuildTest(unittest.TestCase):
    def test_only_test_metadata_changes_and_repeat_is_safe(self):
        original = {
            'CFBundleDisplayName': 'BREBO OnSite',
            'OnSiteAPIBaseURL': 'https://sboffice.brebobv.nl',
            'CFBundleVersion': '$(CURRENT_PROJECT_VERSION)',
            'CFBundleURLTypes': [{'CFBundleURLSchemes': ['brebo-onsite']}],
            'NSLocationAlwaysAndWhenInUseUsageDescription': 'unchanged',
        }
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'Info.plist'
            path.write_bytes(plistlib.dumps(original))
            module.configure(path)
            expected = dict(original, OnSiteAPIBaseURL='https://brebobv.nl/office-test',
                            OnSiteEnvironment='office-test', CFBundleDisplayName='OnSite Test')
            self.assertEqual(plistlib.loads(path.read_bytes()), expected)
            first = path.read_bytes()
            module.configure(path)
            self.assertEqual(path.read_bytes(), first)

    def test_unknown_app_or_endpoint_is_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'Info.plist'
            for name, url in [('Another App', 'https://sboffice.brebobv.nl'),
                              ('BREBO OnSite', 'https://unknown.example')]:
                path.write_bytes(plistlib.dumps({'CFBundleDisplayName': name, 'OnSiteAPIBaseURL': url}))
                before = path.read_bytes()
                with self.assertRaises(ValueError):
                    module.configure(path)
                self.assertEqual(path.read_bytes(), before)


if __name__ == '__main__':
    unittest.main()
