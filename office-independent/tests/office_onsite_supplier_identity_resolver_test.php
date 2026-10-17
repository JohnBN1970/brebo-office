<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierIdentityResolver.php';

use Brebo\Office\Inzet\OnSiteSupplierIdentityResolver;

$rows = [
  ['sheet'=>'wk35','cell'=>'D2','person_label'=>'Monteur A','project_label'=>'Project A',
   'date'=>'2026-08-24','minutes'=>480,'review_required'=>false],
  ['sheet'=>'wk35','cell'=>'E2','person_label'=>'Monteur A','project_label'=>'Project A',
   'date'=>'2026-08-25','minutes'=>480,'review_required'=>true,'comment'=>'4 uur elders'],
  ['sheet'=>'wk35','cell'=>'D3','person_label'=>'Onbekend','project_label'=>'Project A',
   'date'=>'2026-08-24','minutes'=>480,'review_required'=>false],
];
$result = (new OnSiteSupplierIdentityResolver())->resolve($rows,11,['Monteur A'=>21],['Project A'=>31]);
if (count($result['rows']) !== 1 || count($result['issues']) !== 2
  || $result['rows'][0]['supplier_id'] !== 11
  || $result['rows'][0]['person_id'] !== 21
  || $result['issues'][0]['reason'] !== 'source_note_requires_review'
  || $result['issues'][1]['reason'] !== 'person_mapping_unresolved') {
  throw new RuntimeException('Unconfirmed identity or annotated hours passed review gate.');
}
echo "OnSite supplier identity resolver OK\n";
