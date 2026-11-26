<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateForm.php';
use Brebo\Office\Buildings\OfficeBuildingCreateForm;
if (OfficeBuildingCreateForm::searchQuery(['street_address'=>' Loevesteinlaan 91 ','city'=>'Den Haag','postal_code'=>'']) !== 'Loevesteinlaan 91 Den Haag') throw new RuntimeException('Address query mismatch');
if (OfficeBuildingCreateForm::searchQuery(['postal_code'=>'1234 AB','street_address'=>'Teststraat 1']) !== 'Teststraat 1 1234 AB') throw new RuntimeException('Postal query mismatch');
putenv('OFFICE_BUILDING_BAG_SCHEMA_VERIFIED=enabled');
$html=OfficeBuildingCreateForm::render(['street_address'=>'<test>']);
if (str_contains($html,'pdok_query') || !str_contains($html,'value="search_pdok"') || !str_contains($html,'&lt;test&gt;')) throw new RuntimeException('Form contract mismatch');
echo "OfficeBuildingCreateFormTest OK\n";
