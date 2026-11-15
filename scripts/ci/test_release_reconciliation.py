import importlib.util
from pathlib import Path
import tempfile
import unittest
spec=importlib.util.spec_from_file_location('release',Path(__file__).resolve().parents[1]/'deployment/prepare_office_test_release.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class ReconciliationTest(unittest.TestCase):
    def test_preserve_overlay_and_merge_disjoint_changes(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            for name in ('active','incoming','baseline'):(root/name/'src').mkdir(parents=True)
            base='first\nsecond\nthird\nfourth\nfifth\n'
            for name,content in [('baseline',base),('active',base.replace('first','runtime')),('incoming',base.replace('fifth','repository'))]:
                (root/name/'src/file.php').write_text(content)
            module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline')
            merged=(root/'candidate/src/file.php').read_text()
            self.assertIn('runtime',merged);self.assertIn('repository',merged)
            self.assertEqual((root/'active/src/file.php').read_text(),base.replace('first','runtime'))
    def test_conflict_never_changes_active(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            for name,content in [('baseline','original'),('active','runtime'),('incoming','repository')]:
                (root/name/'src').mkdir(parents=True);(root/name/'src/file.php').write_text(content)
            with self.assertRaisesRegex(RuntimeError,'RELEASE_MERGE_CONFLICT'):
                module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline')
            self.assertEqual((root/'active/src/file.php').read_text(),'runtime')
    def test_unknown_collision_is_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            for name in ('active','incoming','baseline'):(root/name/'src').mkdir(parents=True)
            (root/'active/src/file.php').write_text('runtime');(root/'incoming/src/file.php').write_text('source')
            with self.assertRaisesRegex(RuntimeError,'UNTRACKED_RUNTIME_COLLISION'):
                module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline')

if __name__=='__main__':unittest.main()
