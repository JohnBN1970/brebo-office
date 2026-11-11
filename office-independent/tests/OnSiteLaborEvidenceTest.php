<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/OnSiteLaborEvidence.php';
use Brebo\Office\WorkingBudget\OnSiteLaborEvidence as Labor;
$missing=Labor::summarize(['available'=>false],7);
if ($missing['duration_seconds']!==null || $missing['labor_actual_cents']!==null) throw new RuntimeException('Missing labor source became zero.');
$hours=Labor::summarize(['available'=>true,'sessions'=>[
    ['project_id'=>'7','duration_seconds'=>3600],['project_id'=>7,'duration_seconds'=>1800]],
    'exceptions'=>[]],7);
if ($hours['duration_seconds']!==5400 || $hours['session_count']!==2
    || $hours['state']!=='provisional' || $hours['labor_actual_cents']!==null) throw new RuntimeException('Labor evidence incorrect.');
try {
    Labor::summarize(['available'=>true,'sessions'=>[['project_id'=>8,'duration_seconds'=>3600]],'exceptions'=>[]],7);
    throw new RuntimeException('Cross-project evidence accepted.');
} catch (InvalidArgumentException $expected) {}
echo "ONSITE_LABOR_EVIDENCE_TEST=PASS\n";
