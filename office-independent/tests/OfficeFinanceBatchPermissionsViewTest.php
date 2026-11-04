<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionCatalog.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionsView.php';
use Brebo\Office\Finance\OfficeFinanceBatchPermissionCatalog as Catalog;
use Brebo\Office\Finance\OfficeFinanceBatchPermissionsView as View;
$checks=0;
$assert=static function(bool $condition,string $message) use (&$checks): void {
  if (!$condition) throw new RuntimeException($message);
  ++$checks;
};
$empty=View::render([]);
$assert(str_contains($empty,'Betaalbatches aanmaken'),'Create label');
$assert(str_contains($empty,'Betaalbatches controleren'),'Review label');
$assert(str_contains($empty,'Betaalbatches vrijgeven'),'Release label');
$assert(substr_count($empty,'Niet toegekend')===3,'All denied by default');
$all=View::render(Catalog::permissions());
$assert(substr_count($all,'<td>Toegekend</td>')===3,'All three explicit grants shown');
$onlyCreate=View::render([Catalog::CREATE,'<script>alert(1)</script>']);
$assert(substr_count($onlyCreate,'<td>Toegekend</td>')===1,'Unknown grant ignored');
$assert(!str_contains($onlyCreate,'<script>'),'Untrusted grant not rendered');
$assert(!str_contains($onlyCreate,'<form'),'View does not offer mutation form');
echo "OfficeFinanceBatchPermissionsViewTest: $checks checks passed\n";
