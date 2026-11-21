<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingColumnResolver.php';
use Brebo\Office\Buildings\OfficeBuildingColumnResolver as Resolver;
$legacy=Resolver::fromNames(['id','name','address_line','postal_code','city']);
if ($legacy !== ['name'=>'name','address'=>'address_line']) throw new RuntimeException('Legacy building schema not detected');
$native=Resolver::fromNames(['id','building_name','street_address','postal_code','city']);
if ($native !== ['name'=>'building_name','address'=>'street_address']) throw new RuntimeException('Native building schema not detected');
$mixed=Resolver::fromNames(['id','name','building_name','address_line','street_address','postal_code','city']);
if ($mixed !== $native) throw new RuntimeException('Canonical building fields not preferred');
try { Resolver::fromNames(['id','name','postal_code','city']); throw new RuntimeException('Missing address field accepted'); }
catch (RuntimeException $e) { if ($e->getMessage()==='Missing address field accepted') throw $e; }
echo "OFFICE_BUILDING_COLUMN_RESOLVER_OK\n";
