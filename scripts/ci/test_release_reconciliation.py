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
    def test_known_git_ancestor_onsite_conflict_preserves_backup(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            relative=Path('src/Inzet/OfficeOnSiteApprovalService.php')
            for name,content in [('baseline','original'),('active','runtime'),('incoming','repository')]:
                file=root/name/relative
                file.parent.mkdir(parents=True)
                file.write_text(content)
            trusted={module.git_blob_id(b'runtime')}
            module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline',trusted)
            self.assertEqual((root/'candidate'/relative).read_text(),'repository')
            self.assertEqual((root/'candidate/.development-runtime-backups'/relative).read_text(),'runtime')
            self.assertEqual((root/'active'/relative).read_text(),'runtime')

    def test_unrecognized_onsite_conflict_stays_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            relative=Path('src/Inzet/OfficeOnSiteApprovalService.php')
            for name,content in [('baseline','original'),('active','runtime'),('incoming','repository')]:
                file=root/name/relative
                file.parent.mkdir(parents=True)
                file.write_text(content)
            with self.assertRaisesRegex(RuntimeError,'RELEASE_MERGE_CONFLICT'):
                module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline',{'not-a-blob'})
            self.assertEqual((root/'active'/relative).read_text(),'runtime')

    def test_calc_path_ancestor_conflict_is_backed_up(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            relative=Path('src/Calculation/OfficeCalcCrmTransferService.php')
            for name,content in [('baseline','original'),('active','runtime'),('incoming','repository')]:
                file=root/name/relative
                file.parent.mkdir(parents=True)
                file.write_text(content)
            trusted={(str(relative),module.git_blob_id(b'runtime'))}
            module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline',known_calc_path_blobs=trusted)
            self.assertEqual((root/'candidate'/relative).read_text(),'repository')
            self.assertEqual((root/'candidate/.development-runtime-backups'/relative).read_text(),'runtime')
            self.assertEqual((root/'active'/relative).read_text(),'runtime')

    def test_calc_path_ancestor_wrong_path_is_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            relative=Path('src/Calculation/OfficeCalcCrmTransferService.php')
            for name,content in [('baseline','original'),('active','runtime'),('incoming','repository')]:
                file=root/name/relative
                file.parent.mkdir(parents=True)
                file.write_text(content)
            trusted={('src/Calculation/Other.php',module.git_blob_id(b'runtime'))}
            with self.assertRaisesRegex(RuntimeError,'RELEASE_MERGE_CONFLICT'):
                module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline',known_calc_path_blobs=trusted)
            self.assertEqual((root/'active'/relative).read_text(),'runtime')

    def test_unknown_collision_is_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            for name in ('active','incoming','baseline'):(root/name/'src').mkdir(parents=True)
            (root/'active/src/file.php').write_text('runtime');(root/'incoming/src/file.php').write_text('source')
            with self.assertRaisesRegex(RuntimeError,'UNTRACKED_RUNTIME_COLLISION'):
                module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline')


    def test_office_test_authoritative_mode_replaces_source_and_backs_up_both_collisions(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for name in ('active', 'incoming', 'baseline'):
                (root / name / 'src').mkdir(parents=True)
            (root / 'baseline/src/tracked.php').write_text('baseline')
            (root / 'active/src/tracked.php').write_text('runtime-customization')
            (root / 'incoming/src/tracked.php').write_text('develop-source')
            (root / 'active/src/untracked.php').write_text('previous-server-source')
            (root / 'incoming/src/untracked.php').write_text('new-develop-source')
            (root / 'active/src/runtime-only.php').write_text('runtime-only-dependency')
            result = module.reconcile(
                root/'active', root/'candidate', root/'incoming', root/'baseline',
                office_test_authoritative=True
            )
            self.assertEqual(result['updated'], 2)
            for rel, expected, backup in [
                ('tracked.php','develop-source','runtime-customization'),
                ('untracked.php','new-develop-source','previous-server-source'),
            ]:
                self.assertEqual((root/'candidate/src'/rel).read_text(), expected)
                self.assertEqual((root/'candidate/.development-runtime-backups/src'/rel).read_text(), backup)
            self.assertEqual((root/'active/src/tracked.php').read_text(), 'runtime-customization')
            self.assertEqual((root/'active/src/untracked.php').read_text(), 'previous-server-source')
            self.assertEqual((root/'candidate/src/runtime-only.php').read_text(), 'runtime-only-dependency')

    def test_native_module_routes_replace_old_maps_and_preserve_runtime_modules(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            for area in ['active','incoming','baseline']:
                (root/area/'modules/sales').mkdir(parents=True)
            (root/'active/modules/sales/routes.php').write_text('old-versioned-controller')
            (root/'incoming/modules/sales/routes.php').write_text('canonical-controller')
            (root/'active/modules/runtime').mkdir()
            (root/'active/modules/runtime/routes.php').write_text('keep-runtime')
            module.reconcile(root/'active',root/'candidate',root/'incoming',root/'baseline',office_test_authoritative=True)
            self.assertEqual((root/'candidate/modules/sales/routes.php').read_text(),'canonical-controller')
            self.assertEqual((root/'candidate/modules/runtime/routes.php').read_text(),'keep-runtime')
            self.assertEqual((root/'candidate/.development-runtime-backups/modules/sales/routes.php').read_text(),'old-versioned-controller')

if __name__=='__main__':unittest.main()
