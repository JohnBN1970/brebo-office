<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteSupplierHoursReconciliation.php';

use Brebo\Office\Inzet\OnSiteSupplierHoursReconciliation;

$check = new OnSiteSupplierHoursReconciliation();
$approved = [['person_id'=>7,'approved_minutes'=>2400],['person_id'=>8,'approved_minutes'=>2220]];
$invoice = [['person_id'=>7,'invoiced_minutes'=>2400],['person_id'=>8,'invoiced_minutes'=>2400]];
$result = $check->compare($approved,$invoice);
if ($result['status'] !== 'requires_review' || $result['difference_minutes'] !== 180) throw new RuntimeException('Invoice discrepancy missed.');
if ($result['rows'][1]['person_id'] !== 8 || $result['rows'][1]['difference_minutes'] !== 180) throw new RuntimeException('Worker mismatch missed.');
$matched = $check->compare($approved,[['person_id'=>8,'invoiced_minutes'=>2220],['person_id'=>7,'invoiced_minutes'=>2400]]);
if ($matched['status'] !== 'hours_match') throw new RuntimeException('Equal hours rejected.');
$offsetting = $check->compare($approved,[['person_id'=>7,'invoiced_minutes'=>2220],['person_id'=>8,'invoiced_minutes'=>2400]]);
if ($offsetting['status'] !== 'requires_review' || $offsetting['difference_minutes'] !== 0) throw new RuntimeException('Offsetting person discrepancies hidden.');
if ($check->compare([],[])['status'] !== 'no_evidence') throw new RuntimeException('Missing evidence accepted.');
echo "OnSite supplier hours reconciliation OK\n";
