<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingAddressRepository.php';
use Brebo\Office\Buildings\OfficeBuildingAddressRepository;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  echo "SQLite unavailable; inventory validation checks skipped\n"; exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$repo=new OfficeBuildingAddressRepository($db);
$invalid=[
  [0,'K01','Kozijn','2','st'],
  [1,'','Kozijn','2','st'],
  [1,'K01','Kozijn','0','st'],
  [1,'K01','Kozijn','-2','st'],
  [1,'K01','Kozijn','2.1234','st'],
  [1,'K01','Kozijn','2',''],
  [1,'K01','Kozijn','1e3','st']
];
foreach ($invalid as $args) {
  try {
    $repo->addInventoryItem(...$args);
    fwrite(STDERR,'Accepted invalid inventory row: '.json_encode($args)."\n");
    exit(1);
  } catch (InvalidArgumentException $expected) {}
}
echo "Office building inventory input validation OK\n";
