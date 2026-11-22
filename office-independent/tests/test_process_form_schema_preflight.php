<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormSchemaPreflight.php';
use Brebo\Office\Process\OfficeProcessFormSchemaPreflight as P;
if (!class_exists(P::class)) throw new RuntimeException('Missing preflight');
$source=file_get_contents(dirname(__DIR__).'/src/Process/OfficeProcessFormSchemaPreflight.php');
foreach (['office_process_form_instances','office_process_form_events','office_process_form_evidence','SHOW COLUMNS','ready','errors'] as $needle) {
    if (!str_contains($source,$needle)) throw new RuntimeException('Missing schema readiness check: '.$needle);
}
if (preg_match('/\\b(?:CREATE|ALTER|DROP|TRUNCATE|INSERT|UPDATE|DELETE)\\s+TABLE\\b/i',$source)) throw new RuntimeException('Preflight may not mutate schema');
echo "Process form schema preflight contracts OK\n";
