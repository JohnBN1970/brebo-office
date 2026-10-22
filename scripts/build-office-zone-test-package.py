"""Build a self-contained, fixed-scope Office-test zone package from reviewed sources."""
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
relative_files = [
    "public/office-onsite-project-map.php", "public/office-onsite-zone-create.php",
    "src/Buildings/OfficeBuildingRepository.php", "src/Buildings/OfficeBuildingZoneRepository.php",
    "src/Buildings/OfficeBuildingZoneReadiness.php", "src/Inzet/OnSiteOfficeCircleCreateService.php",
    "src/Inzet/OnSiteOfficeZoneFormToken.php", "src/Inzet/OnSiteOfficeZonePostAction.php",
    "src/Inzet/OnSiteOfficeZoneProjectAccess.php",
]
files = {p: (repo / "office-independent" / p).read_text(encoding="utf-8") for p in relative_files}
def fragment(path):
    text = path.read_text(encoding="utf-8")
    return re.sub(r"^\s*<\?php\s*declare\(strict_types=1\);\s*", "", text, count=1)

installer = fragment(repo / "office-independent/deployment/hostinger/OfficeZoneTestDeployment.php")
readiness = fragment(repo / "office-independent/src/Buildings/OfficeBuildingZoneReadiness.php")
encoded = base64.b64encode(json.dumps(files, ensure_ascii=False, separators=(",", ":")).encode()).decode()
encoded_php = "'" + "' .\n    '".join(textwrap.wrap(encoded, 120)) + "'"
wrapper = r'''
namespace Brebo\Office\Deployment;
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
try {
  $base='/home/u213420663/domains/brebobv.nl/office_calc_test';
  $root=$base.'/release-office-test-20261018/private';
  $router='/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';
  $config=$base.'/config/config.php';
  if (!is_file($config)) throw new \RuntimeException('test_configuration_not_found');
  $files=json_decode(base64_decode(__PAYLOAD__,true),true,512,JSON_THROW_ON_ERROR);
  $guard=static function(array $c) use ($root):void {
    if (($c['OFFICE_INTAKE_DB_USER'] ?? '')!=='u213420663_office_test'
      || ($c['OFFICE_PUBLIC_HOST'] ?? '')!=='brebobv.nl'
      || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$c['OFFICE_INTAKE_PDO_DSN'] ?? '')) {
      throw new \RuntimeException('isolated_test_database_and_host_required');
    }
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
      'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $dependency) {
      $path=$root.'/src/Intake/'.$dependency;
      if (!is_file($path) || realpath($path)!==$path) throw new \RuntimeException('existing_session_dependency_unavailable:'.$dependency);
      token_get_all(file_get_contents($path),TOKEN_PARSE);
    }
    $db=new \PDO($c['OFFICE_INTAKE_PDO_DSN'],$c['OFFICE_INTAKE_DB_USER'],$c['OFFICE_INTAKE_DB_PASSWORD'] ?? '',
      [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_EMULATE_PREPARES=>false]);
    \Brebo\Office\Buildings\OfficeBuildingZoneReadiness::requireReady($db);
  };
  $protected=[
    'public/office-project.php'=>'66a97cf18e06829c026ceb19547c8ea1ccb7fe8cee067c433f935d54155cc334',
    'src/Project/OfficeNativeProjectAccess.php'=>'0dff0f45175cd4652e69f91df794dc38e74476cc60688a1bdedbad52249c7e97',
  ];
  $deployment=new OfficeZoneTestDeployment($base,$root,$router,$config,
    '30ceac05b2c1243e86e677422d8ae27104ceaeaf85cf04a6dd574414b5313c0b',$protected,$files,$guard);
  $result=$deployment->run($argv[1] ?? '--check');
  $result['package_sha256']=hash_file('sha256',__FILE__);
  echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
} catch (\Throwable $e) {
  echo json_encode(['status'=>'BLOCK','reason'=>$e instanceof \PDOException ? 'test_database_readiness_unavailable' : $e->getMessage()],
    JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
  exit(1);
}
'''
source = "<?php\ndeclare(strict_types=1);\n" + installer + "\n" + readiness + "\n" + wrapper.replace("__PAYLOAD__", encoded_php)
name = "office-zone-testdeploy-20261020.php"
package = out / name
package.write_text(source, encoding="utf-8", newline="\n")
digest = hashlib.sha256(source.encode()).hexdigest()
manifest = {
    "source_commit": args.commit, "package": name, "sha256": digest,
    "files": {p: hashlib.sha256(content.encode()).hexdigest() for p, content in files.items()},
    "excluded_existing_files": ["public/office-project.php", "src/Project/OfficeNativeProjectAccess.php"],
    "database_writes": False,
}
(out / "office-zone-testdeploy-manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
remote = "/home/u213420663/domains/brebobv.nl/office_calc_test/" + name
paste = (
    "if [ -e '" + remote + "' ]; then\n"
    "  printf '%s\\n' 'STOP: pakketbestand bestaat al; niets overschreven.'\n"
    "else\n"
    "  (umask 077; cat > '" + remote + "' <<'OFFICE_ZONE_PACKAGE'\n"
    + source + "\nOFFICE_ZONE_PACKAGE\n"
    "  )\n"
    "  php '" + remote + "' --check\n"
    "fi\n"
)
(out / "office-test-zone-pakket-in-moba.txt").write_text(paste, encoding="utf-8", newline="\n")
print(json.dumps({"package": str(package), "sha256": digest, "files": len(files)}))

