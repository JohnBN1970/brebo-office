"""Build a guarded update package for an already enabled Office-test zone installation."""
import argparse
import base64
import hashlib
import json
import pathlib
import re
import textwrap

parser = argparse.ArgumentParser()
parser.add_argument("--output-dir", required=True)
parser.add_argument("--commit", default="unrecorded")
args = parser.parse_args()
repo = pathlib.Path(__file__).resolve().parents[1]
out = pathlib.Path(args.output_dir).resolve()
out.mkdir(parents=True, exist_ok=True)
targets = [
    "public/office-onsite-project-map.php",
    "src/Buildings/OfficeBuildingZoneRepository.php",
]
files = {path: (repo / "office-independent" / path).read_text(encoding="utf-8") for path in targets}
installer = (repo / "office-independent/deployment/hostinger/OfficeZoneTestUpdate.php").read_text(encoding="utf-8")
installer = re.sub(r"^\s*<\?php\s*declare\(strict_types=1\);\s*", "", installer, count=1)
payload = base64.b64encode(json.dumps(files, ensure_ascii=False, separators=(",", ":")).encode()).decode()
literal = "'" + "' .\n  '".join(textwrap.wrap(payload, 120)) + "'"
wrapper = r"""
namespace Brebo\Office\Deployment;
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
try {
  $base='/home/u213420663/domains/brebobv.nl/office_calc_test';
  $root=$base.'/release-office-test-20261018/private';
  $router='/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';
  $config=$base.'/config/config.php';
  $files=json_decode(base64_decode(__PAYLOAD__,true),true,512,JSON_THROW_ON_ERROR);
  $updater=new OfficeZoneTestUpdate($base,$root,$router,$config,$files);
  $result=$updater->run($argv[1]??'--check');
  $result['package_sha256']=hash_file('sha256',__FILE__);
  echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
} catch (\Throwable $e) {
  echo json_encode(['status'=>'BLOCK','reason'=>$e->getMessage()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
  exit(1);
}
"""
source = "<?php\ndeclare(strict_types=1);\n" + installer + "\n" + wrapper.replace("__PAYLOAD__", literal)
name = "office-zone-testupdate-20261022.php"
package = out / name
package.write_text(source, encoding="utf-8", newline="\n")
digest = hashlib.sha256(source.encode()).hexdigest()
manifest = {
    "source_commit": args.commit,
    "package": name,
    "sha256": digest,
    "files": {path: hashlib.sha256(content.encode()).hexdigest() for path, content in files.items()},
    "changes": "existing Office-test map and zone repository only",
    "database_writes": False,
    "router_writes": False,
    "config_writes": False,
}
(out / "office-zone-testupdate-manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
print(json.dumps({"package": str(package), "sha256": digest, "files": len(files)}))
