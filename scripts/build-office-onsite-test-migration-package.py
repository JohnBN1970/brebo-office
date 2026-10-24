"""Build a manual-only Office-test migration package; never contacts a server."""
import argparse
import hashlib
import json
from pathlib import Path
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output-dir', required=True)
parser.add_argument('--commit', required=True)
args = parser.parse_args()
repo = Path(__file__).resolve().parents[1]
out = Path(args.output_dir).resolve()
out.mkdir(parents=True, exist_ok=True)
paths = {
    'deployment/hostinger/OfficeOnSiteTestMigration.php': 'office-independent/deployment/hostinger/OfficeOnSiteTestMigration.php',
    'deployment/hostinger/office-onsite-test-migrate.php': 'office-independent/deployment/hostinger/office-onsite-test-migrate.php',
    'src/Inzet/OnSiteEventInboxSchema.php': 'office-independent/src/Inzet/OnSiteEventInboxSchema.php',
    'src/Inzet/OnSiteDeviceTokenSchema.php': 'office-independent/src/Inzet/OnSiteDeviceTokenSchema.php',
    'README.md': 'docs/OFFICE_ONSITE_TEST_MIGRATION_2026-10-22.md',
}
payload = {target: (repo / source).read_bytes() for target, source in paths.items()}
manifest = {'commit': args.commit, 'activation': 'manual_only_no_flag_changes',
            'files': {p: hashlib.sha256(data).hexdigest() for p, data in payload.items()}}
payload['manifest.json'] = (json.dumps(manifest, indent=2) + '\n').encode()
archive = out / 'office-onsite-test-migration-20261022.zip'
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for name, content in payload.items():
        info = zipfile.ZipInfo(name, (2026, 10, 22, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100600 << 16
        z.writestr(info, content)
(out / 'SHA256SUMS.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n', encoding='utf-8')
print(archive)
