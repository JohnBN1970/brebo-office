<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OnSiteOfficeProjectMapSeed.php';
use Brebo\Office\Inzet\OnSiteOfficeProjectMapSeed;
$seed=OnSiteOfficeProjectMapSeed::fromAuthorizedProject(['id'=>17,'latitude'=>51.5,'longitude'=>4.3]);
if ($seed!==['project_id'=>'17','latitude'=>51.5,'longitude'=>4.3]) throw new RuntimeException('Valid map seed failed');
foreach ([['id'=>17,'latitude'=>null,'longitude'=>4.3],['id'=>0,'latitude'=>51.5,'longitude'=>4.3],['id'=>17,'latitude'=>91,'longitude'=>4.3],['id'=>17,'latitude'=>51.5,'longitude'=>INF]] as $bad) {
  try {OnSiteOfficeProjectMapSeed::fromAuthorizedProject($bad);throw new RuntimeException('Invalid map seed accepted');}
  catch (InvalidArgumentException $expected) {}
}
echo "Office project map seed validation OK\n";
