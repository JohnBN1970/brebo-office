"""Build a review-only Office-test source/test bundle. Never install or enable it."""
import argparse
import hashlib
import json
import pathlib
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output-dir', required=True)
parser.add_argument('--commit', required=True)
args = parser.parse_args()
repo = pathlib.Path(__file__).resolve().parents[1]
out = pathlib.Path(args.output_dir).resolve()
out.mkdir(parents=True, exist_ok=True)
paths = sorted({
    *repo.glob('office-independent/src/Inzet/*.php'),
    *repo.glob('office-independent/src/Buildings/*.php'),
    *repo.glob('office-independent/public/onsite-*.php'),
    *repo.glob('office-independent/public/office-onsite-*.php'),
    *repo.glob('office-independent/tests/OfficeOnSite*.php'),
    *repo.glob('ios/CrewPOC/Sources/OnSite*.swift'),
    repo / 'ios/CrewPOC/Sources/CrewContentView.swift',
    repo / 'docs/OFFICE_ONSITE_INTEGRATION_ACCEPTANCE_2026-10-23.md',
})
files = {str(p.relative_to(repo)): p.read_bytes() for p in paths}
assert all('OfficeNativeProjectAccess' not in p for p in files)
manifest = {
    'source_commit': args.commit,
    'purpose': 'Office-test review only; no installer, automatic migration, import or enablement',
    'mount': '/office-test',
    'private_root': '/home/u213420663/domains/brebobv.nl/office_calc_test/release-office-test-20261018/private',
    'database_writes': False, 'config_writes': False,
    'preserve_server_adapter': 'OfficeNativeProjectAccess with canRead AND canManage',
    'files': {p: hashlib.sha256(data).hexdigest() for p, data in files.items()},
}
manifest_bytes = (json.dumps(manifest, indent=2) + '\n').encode()
name = 'office-onsite-integration-review-20261023.zip'
with zipfile.ZipFile(out / name, 'w', compression=zipfile.ZIP_DEFLATED) as bundle:
    for path, data in files.items():
        entry = zipfile.ZipInfo(path, (2026, 10, 23, 0, 0, 0))
        entry.compress_type = zipfile.ZIP_DEFLATED
        bundle.writestr(entry, data)
    bundle.writestr('manifest.json', manifest_bytes)
(out / 'manifest.json').write_bytes(manifest_bytes)
with zipfile.ZipFile(out / name) as bundle:
    for path, digest in manifest['files'].items():
        assert hashlib.sha256(bundle.read(path)).hexdigest() == digest, path
print(json.dumps({'package': name, 'files': len(files), 'source_commit': args.commit,
                  'sha256': hashlib.sha256((out / name).read_bytes()).hexdigest()}))
