"""Build the isolated Office-test zone edit extension. Never targets production."""
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
    "public/office-onsite-zone-update.php",
    "src/Inzet/OnSiteOfficeCircleUpdateService.php",
    "src/Inzet/OnSiteOfficeZoneUpdatePostAction.php",
]
files = {p: (repo / "office-independent" / p).read_text(encoding="utf-8") for p in targets}
installer = (repo / "office-independent/deployment/hostinger/OfficeZoneEditTestDeployment.php").read_text(encoding="utf-8")
installer = re.sub(r"^\s*<\?php\s*declare\(strict_types=1\);\s*", "", installer, count=1)
encoded = base64.b64encode(json.dumps(files, ensure_ascii=False, separators=(",", ":")).encode()).decode()
literal = "'" + "' .\n  '".join(textwrap.wrap(encoded, 120)) + "'"
wrapper = r"""
namespace Brebo\Office\Deployment;
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
try {
  $base='/home/u213420663/domains/brebobv.nl/office_calc_test';
  $root=$base.'/release-office-test-20261018/private';
  $router='/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';
  $config=$base.'/config/config.php';
  $files=json_decode(base64_decode(__PAYLOAD__,true),true,512,JSON_THROW_ON_ERROR);
  $deployment=new OfficeZoneEditTestDeployment($base,$root,$router,$config,$files);
  $result=$deployment->run($argv[1]??'--check');
  $result['package_sha256']=hash_file('sha256',__FILE__);
  echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
} catch (\Throwable $e) {
  echo json_encode(['status'=>'BLOCK','reason'=>$e->getMessage()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
  exit(1);
}
"""
source = "<?php\ndeclare(strict_types=1);\n" + installer + "\n" + wrapper.replace("__PAYLOAD__", literal)
name = "office-zone-edit-testdeploy-20261022.php"
package = out / name
package.write_text(source, encoding="utf-8", newline="\n")
manifest = {
    "source_commit": args.commit,
    "package": name,
    "sha256": hashlib.sha256(source.encode()).hexdigest(),
    "files": {p: hashlib.sha256(s.encode()).hexdigest() for p, s in files.items()},
    "router": "test-only route appended last",
    "database_writes": False,
    "config_writes": False,
}
(out / "office-zone-edit-testdeploy-manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
print(json.dumps({"package": str(package), "sha256": manifest["sha256"], "files": len(files)}))
