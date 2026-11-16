<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcHeadingCode.php';
use Brebo\Office\Calculation\OfficeCalcHeadingCode;

foreach ([
 ['nlsfb','21','Buitenwanden'],
 ['stabu','22.10','Metselwerk'],
 ['free','A-01','Werkvoorbereiding'],
] as [$system,$code,$title]) {
    $heading=OfficeCalcHeadingCode::normalize($system,$code,$title);
    if (OfficeCalcHeadingCode::key($heading)!==$system.':'.$code) throw new RuntimeException('Hoofdstuksleutel onjuist');
}
if (OfficeCalcHeadingCode::key(OfficeCalcHeadingCode::normalize('nlsfb','21','Wanden'))
    ===OfficeCalcHeadingCode::key(OfficeCalcHeadingCode::normalize('stabu','21','Wanden'))) {
    throw new RuntimeException('Stelsels mogen niet samenvallen');
}
foreach ([['labor','21','Arbeid'],['nlsfb','A-1','Ongeldig'],['stabu','22;DROP','Ongeldig'],['free','X',''],['free',str_repeat('x',41),'Te lang'],['free','X'."\n".'Y','Controle']] as $invalid) {
    try { OfficeCalcHeadingCode::normalize(...$invalid); }
    catch (DomainException) { continue; }
    throw new RuntimeException('Ongeldige classificatie geaccepteerd');
}
echo "CALC_HEADING_CODES=PASS\n";
