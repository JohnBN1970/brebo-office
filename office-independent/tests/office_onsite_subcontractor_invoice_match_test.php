<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteSubcontractorInvoiceMatch.php';

use Brebo\Office\Inzet\OnSiteSubcontractorInvoiceMatch;

$match = new OnSiteSubcontractorInvoiceMatch();
$hours = [['project_id'=>10,'person_id'=>4,'eligible'=>true,'seconds'=>27000]];
$exact = $match->compare($hours,10,4,5500,41250);
if (!$exact['matched'] || $exact['expected_cents'] !== 41250 || $exact['approved_seconds'] !== 27000) throw new RuntimeException('Exact match failed.');
$diff = $match->compare($hours,10,4,5500,42000);
if ($diff['matched'] || $diff['difference_cents'] !== 750) throw new RuntimeException('Difference not detected.');
$pending = $match->compare([array_replace($hours[0],['eligible'=>false])],10,4,5500,41250);
if ($pending['matched']) throw new RuntimeException('Pending hours matched.');
if ($match->compare([],10,4,5500,0)['matched']) throw new RuntimeException('Empty hours matched.');
try {
  $match->compare($hours,11,4,5500,41250);
  throw new RuntimeException('Wrong project accepted.');
} catch (DomainException $expected) {}
echo "OnSite subcontractor invoice matching OK\n";
