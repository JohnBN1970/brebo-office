"""Build a self-contained, manual Office-test code update from reviewed server evidence."""
import argparse
import base64
import hashlib
import json
import pathlib
import re
import textwrap

parser = argparse.ArgumentParser()
parser.add_argument('--output-dir', required=True)
parser.add_argument('--commit', required=True)
args = parser.parse_args()
repo = pathlib.Path(__file__).resolve().parents[1]
out = pathlib.Path(args.output_dir).resolve()
out.mkdir(parents=True, exist_ok=True)
baseline = json.loads((repo / 'office-independent/deployment/hostinger/office-onsite-runtime-observed-20261023.json').read_text())
sources = {p: (repo / 'office-independent' / p).read_text(encoding='utf-8') for p in baseline['old']}
assert not any('OfficeNativeProjectAccess' in p for p in sources)
installer = (repo / 'office-independent/deployment/hostinger/OfficeOnSiteRuntimeTestUpdate.php').read_text()
installer = re.sub(r'^\s*<\?php\s*declare\(strict_types=1\);\s*', '', installer, count=1)
payload = base64.b64encode(json.dumps({'sources': sources, 'baseline': baseline}, separators=(',', ':')).encode()).decode()
literal = "'" + "' .\n  '".join(textwrap.wrap(payload, 120)) + "'"
wrapper = r'''
namespace Brebo\Office\Deployment;
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
try {
  $base='/home/u213420663/domains/brebobv.nl/office_calc_test';
  $root=$base.'/release-office-test-20261018/private';
  $data=json_decode(base64_decode(__PAYLOAD__,true),true,64,JSON_THROW_ON_ERROR);
  $mode=$argv[1]??'--check';
  if (in_array($mode,['--apply','--rollback'],true)
    && ($argv[2]??'')!=='--confirm=office-test-onsite-runtime-20261023') {
    throw new \RuntimeException('explicit_test_confirmation_required');
  }
  $result=(new OfficeOnSiteRuntimeTestUpdate($base,$root,
    '/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php',
    $base.'/config/config.php',$data['sources'],$data['baseline']))->run($mode);
  $result['source_commit']='__COMMIT__';
  $result['package_sha256']=hash_file('sha256',__FILE__);
  echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
} catch (\Throwable $error) {
  echo json_encode(['status'=>'BLOCK','reason'=>$error->getMessage()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
  exit(1);
}
'''
# A commit is metadata, never executable source supplied by a caller.
assert re.fullmatch(r'[A-Za-z0-9_-]{1,64}', args.commit)
source = '<?php\ndeclare(strict_types=1);\n' + installer + '\n' + wrapper.replace('__PAYLOAD__', literal).replace('__COMMIT__', args.commit)
name = 'office-onsite-runtime-testupdate-20261023.php'
(out / name).write_text(source, encoding='utf-8', newline='\n')
manifest = {'source_commit': args.commit, 'package': name, 'sha256': hashlib.sha256(source.encode()).hexdigest(),
            'files': {p: hashlib.sha256(code.encode()).hexdigest() for p, code in sources.items()},
            'database_writes': False, 'config_writes': False, 'flag_writes': False,
            'shared_adapter': 'PRESERVED', 'manual_only': True}
(out / 'office-onsite-runtime-testupdate-manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
print(json.dumps({'package': name, 'sha256': manifest['sha256'], 'files': len(sources), 'source_commit': args.commit}))
