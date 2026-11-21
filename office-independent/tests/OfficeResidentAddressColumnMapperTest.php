<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressColumnMapper.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressProposal.php';
use Brebo\Office\Buildings\OfficeResidentAddressColumnMapper as Mapper;
use Brebo\Office\Buildings\OfficeResidentAddressProposal as Proposal;
$row = ['Straatnaam'=>'Marktstraat','Huisnummer'=>'12','Toevoeging'=>'B',
  'Postcode'=>'1234 AB','Woonplaats'=>'Utrecht','Bewonersnaam'=>'Testpersoon',
  'Telefoonnummer'=>'0612345678','E-mailadres'=>'test@example.invalid'];
$mapped=Mapper::map($row);
$proposal=Proposal::fromRecord($mapped,'xlsx','upload-1:row-4');
if ($proposal['status']!=='proposal' || $proposal['resident']['name']!=='Testpersoon'
  || $proposal['address']['addition']!=='B') throw new RuntimeException('Dutch column mapping failed');
$empty=Proposal::fromRecord(Mapper::map(['Straat'=>'Marktstraat','Huisnr'=>'14','Postcode'=>'1234AB','Plaats'=>'Utrecht']), 'csv', 'upload-2:row-1');
if ($empty['resident']['name']!==null) throw new RuntimeException('Resident must be optional');
$conflict=false;
try { Mapper::map(['Straat'=>'A','Straatnaam'=>'B']); }
catch (InvalidArgumentException $e) { $conflict=true; }
if (!$conflict) throw new RuntimeException('Conflicting address columns were accepted');
echo "OFFICE_RESIDENT_ADDRESS_COLUMN_MAPPER_OK\n";
