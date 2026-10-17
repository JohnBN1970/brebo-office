<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteInvoicePeriodControl.php';

use Brebo\Office\Inzet\OnSiteInvoicePeriodControl;

$control = new OnSiteInvoicePeriodControl();
$periodStart = '2026-10-01T00:00:00+00:00';
$periodEnd = '2026-11-01T00:00:00+00:00';
$session = ['started_at'=>'2026-10-15T07:00:00+02:00','ended_at'=>'2026-10-15T16:00:00+02:00','eligible'=>true];
if (!$control->evaluate([$session],$periodStart,$periodEnd)['eligible']) throw new RuntimeException('Valid period rejected.');
if ($control->evaluate([array_replace($session,['eligible'=>false])],$periodStart,$periodEnd)['eligible']) throw new RuntimeException('Unapproved hours accepted.');
$crossing = array_replace($session,['started_at'=>'2026-10-31T23:00:00+00:00','ended_at'=>'2026-11-01T01:00:00+00:00']);
if ($control->evaluate([$crossing],$periodStart,$periodEnd)['reason'] !== 'session_outside_period') throw new RuntimeException('Cross-period session accepted.');
if ($control->evaluate([],$periodStart,$periodEnd)['eligible']) throw new RuntimeException('Empty period accepted.');
echo "OnSite invoice period control OK\n";
