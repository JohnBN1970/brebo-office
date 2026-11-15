from pathlib import Path
import importlib.util
import shutil
import tempfile
import unittest
import yaml
spec=importlib.util.spec_from_file_location('policy',Path(__file__).with_name('check_deployment_policy.py'))
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class PolicyTest(unittest.TestCase):
    def fixture(self,root):
        dest=root/'.github/workflows';dest.mkdir(parents=True)
        for name in ('sboffice-deploy-develop.yml',module.CENTRAL):shutil.copy(module.ROOT/'.github/workflows'/name,dest/name)
    def test_production_push_is_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);self.fixture(root)
            p=root/'.github/workflows/sboffice-deploy-develop.yml'
            p.write_text(p.read_text().replace('  workflow_dispatch:','  push:\n    branches: [develop]\n  workflow_dispatch:',1))
            with self.assertRaises(SystemExit):module.check(root)
    def test_second_remote_pipeline_is_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);self.fixture(root)
            (root/'.github/workflows/rogue.yml').write_text("on: [push]\njobs:\n  deploy:\n    runs-on: ubuntu-latest\n    steps:\n      - run: ssh remote deploy\n")
            with self.assertRaises(SystemExit):module.check(root)
    def test_manual_production_bypass_is_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);self.fixture(root)
            (root/'.github/workflows/maintenance.yml').write_text("on: [workflow_dispatch]\njobs:\n  deploy:\n    runs-on: ubuntu-latest\n    steps:\n      - run: ssh remote 'cd public_html/sboffice && deploy'\n")
            with self.assertRaises(SystemExit):module.check(root)
    def test_production_fixture_is_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);self.fixture(root)
            p=root/'.github/workflows/sboffice-deploy-develop.yml'
            p.write_text(p.read_text()+"\n# acceptance: fixture should never be in production\n")
            with self.assertRaises(SystemExit):module.check(root)
    def test_feature_secrets_are_blocked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);self.fixture(root)
            p=root/'.github/workflows'/module.CENTRAL
            p.write_text(p.read_text().replace("github.ref == 'refs/heads/develop'","github.ref != ''"))
            with self.assertRaises(SystemExit):module.check(root)

if __name__=='__main__':unittest.main()
