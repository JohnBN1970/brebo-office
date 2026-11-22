<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingPdokSaveAction.php';
use Brebo\Office\Buildings\OfficeBuildingPdokClient as Client;
use Brebo\Office\Buildings\OfficeBuildingPdokRequestGuard as Guard;

function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejects(Closure $call): void {
  try { $call(); } catch (DomainException | InvalidArgumentException | UnexpectedValueException $e) { return; }
  throw new RuntimeException('unsafe input accepted');
}
$secret = str_repeat('a', 64); $now = 1800000000;
$server = ['REQUEST_METHOD'=>'POST','HTTP_ORIGIN'=>'https://example.test','HTTP_HOST'=>'example.test','HTTPS'=>'on'];
$token = Guard::token($secret, 7, '/office-test', $now);
$verify = static fn($s, $t, $key=null, $id=7, $mount='/office-test', $at=null) => Guard::verify($s, $t, $key ?? $secret, $id, $mount, 'example.test', $at ?? $now);
$verify($server, $token);
foreach ([['REQUEST_METHOD'=>'GET'],['HTTP_ORIGIN'=>'https://evil.test'],['HTTP_ORIGIN'=>'null'],['HTTP_ORIGIN'=>''],['HTTP_ORIGIN'=>'https://example.test.evil.test'],['HTTP_HOST'=>'evil.test'],['HTTPS'=>'off']] as $bad) rejects(fn()=>$verify(array_replace($server,$bad),$token));
foreach ([null, [], 'bad', substr($token,0,-1).'Z'] as $bad) rejects(fn()=>$verify($server,$bad));
rejects(fn()=>$verify($server,$token,str_repeat('b',64)));
rejects(fn()=>$verify($server,$token,$secret,8));
rejects(fn()=>$verify($server,$token,$secret,7,'/office'));
rejects(fn()=>$verify($server,$token,$secret,7,'/office-test',$now+901));
rejects(fn()=>$verify($server,$token,$secret,7,'/office-test',$now-1));
$doc = ['id'=>'adr-fixture', 'bron'=>'BAG', 'type'=>'adres', 'nummeraanduiding_id'=>'0363200003761447',
  'straatnaam'=>'Dam', 'huis_nlt'=>'1', 'postcode'=>'1012JS', 'woonplaatsnaam'=>'Amsterdam',
  'centroide_ll'=>'POINT(4.8937175 52.37329259)', 'weergavenaam'=>'Dam 1, 1012JS Amsterdam'];
$transport = static function(string $url) use ($doc): array {
  check(str_starts_with($url,'https://api.pdok.nl/bzk/locatieserver/search/v3_1/'), 'wrong PDOK origin');
  return ['response'=>['docs'=>[$doc]]];
};
$client = new Client($transport);
$location = $client->lookup('adr-fixture');
check($location['bag_id']==='0363200003761447' && $location['address']==='Dam 1', 'wrong BAG identity/address');
check($location['lat']>52 && $location['lon']<5, 'coordinates swapped');
check(count($client->search('Dam 1'))===1, 'search missing');
rejects(fn()=>$client->lookup('https://evil.test'));
rejects(fn()=>$client->lookup('adr-other'));
foreach (['bron'=>'BRK','type'=>'weg','nummeraanduiding_id'=>'123','postcode'=>'bad','straatnaam'=>'','centroide_ll'=>'POINT(90 90)'] as $key=>$value) rejects(fn()=>Client::location(array_replace($doc,[$key=>$value])));
$page = file_get_contents(dirname(__DIR__).'/public/office-buildings.php');
check(str_contains($page,"getenv('OFFICE_BUILDING_PDOK_SAVE_ENABLED') === 'enabled'"), 'default-off gate missing');
check(str_contains($page,'OfficeBuildingPdokSaveAction') && str_contains($page,"name=\"csrf\""), 'save wiring missing');
check(str_contains($page,'Zone counts are optional') && str_contains($page,'schema-inspectie'), 'read fallback missing');
echo "PDOK_SECURITY_CHECK_OK\n";
