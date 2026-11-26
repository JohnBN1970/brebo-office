<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficePdokAddressLookup.php';
use Brebo\Office\Buildings\OfficePdokAddressLookup;
$doc=['bron'=>'BAG','type'=>'adres','nummeraanduiding_id'=>'0363200000000001','straatnaam'=>'Teststraat','huis_nlt'=>'12A','postcode'=>'1234AB','woonplaatsnaam'=>'Teststad','centroide_ll'=>'POINT(4.9 52.3)'];
$fixture=static fn(array $docs,int $count)=>json_encode(['response'=>['numFound'=>$count,'docs'=>$docs]],JSON_THROW_ON_ERROR);
$lookup=new OfficePdokAddressLookup(static fn(string $url)=>$fixture([$doc],1));
$row=$lookup->verify($doc['nummeraanduiding_id']);
if ($row['street_address']!=='Teststraat 12A' || $row['postal_code']!=='1234 AB' || $row['latitude']!==52.3 || $row['longitude']!==4.9) throw new RuntimeException('Incorrect canonical address');
if (count($lookup->search('Teststraat'))!==1) throw new RuntimeException('Search failed');
$reject=static function(callable $fn):void {try {$fn();} catch (DomainException|RuntimeException|JsonException $e) {return;} throw new RuntimeException('Expected rejection');};
$reject(static fn()=>$lookup->verify('123'));
$reject(static fn()=>$lookup->verify('0363200000000002'));
$reject(static fn()=>(new OfficePdokAddressLookup(static fn($url)=>$fixture([$doc],2)))->verify($doc['nummeraanduiding_id']));
$reject(static fn()=>(new OfficePdokAddressLookup(static fn($url)=>$fixture([array_replace($doc,['bron'=>'OTHER'])],1)))->verify($doc['nummeraanduiding_id']));
$reject(static fn()=>(new OfficePdokAddressLookup(static fn($url)=>false))->search('Teststraat'));
echo "OfficePdokAddressLookupTest OK\n";
