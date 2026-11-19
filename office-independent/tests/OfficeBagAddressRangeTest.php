<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Buildings/OfficeBagAddressRange.php';

use Brebo\Office\Buildings\OfficeBagAddressRange as Range;

$assert=static function(bool $ok,string $message):void {
  if (!$ok) {fwrite(STDERR,$message."\n");exit(1);}
};
$assert(Range::parse('1','36')===[1,36],'valid range');
foreach ([['0','4'],['6','5'],['1','501'],['1','2a']] as $bad) {
  try {Range::parse($bad[0],$bad[1]);fwrite(STDERR,'invalid range accepted'."\n");exit(1);}
  catch (InvalidArgumentException $expected) {}
}
$docs=[
  ['type'=>'adres','nummeraanduiding_id'=>'bag-10','straatnaam'=>'Teststraat','woonplaatsnaam'=>'Teststad','huisnummer'=>10,'postcode'=>'1234AB'],
  ['type'=>'adres','nummeraanduiding_id'=>'bag-12','straatnaam'=>'Teststraat','woonplaatsnaam'=>'Teststad','huisnummer'=>12,'huisletter'=>'A','postcode'=>'1234AB'],
  ['type'=>'adres','nummeraanduiding_id'=>'bag-12','straatnaam'=>'Teststraat','woonplaatsnaam'=>'Teststad','huisnummer'=>12,'huisletter'=>'A','postcode'=>'1234AB'],
  ['type'=>'adres','nummeraanduiding_id'=>'bag-40','straatnaam'=>'Teststraat','woonplaatsnaam'=>'Teststad','huisnummer'=>40],
  ['type'=>'weg','nummeraanduiding_id'=>'bag-11','straatnaam'=>'Teststraat','woonplaatsnaam'=>'Teststad','huisnummer'=>11],
];
$selected=Range::select($docs,10,20);
$assert(count($selected)===2,'only verified unique BAG addresses in range');
$assert($selected[0]['house_number']===10 && $selected[1]['house_number']===12,'sorted addresses');
$assert($selected[0]['primary_floor']===null && $selected[0]['dwelling_type_id']===null,'optional classification');
echo "Office BAG address range tests OK\n";
