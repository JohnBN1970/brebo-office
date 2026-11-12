<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowBlueprintValidator.php';
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowBlueprintCompiler.php';
$path = __DIR__ . '/../config/workflows/brebo-commercial-v1.json';
$raw = file_get_contents($path);
if (!is_string($raw)) throw new RuntimeException('Missing BREBO workflow');
$definition = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($definition)) throw new RuntimeException('Invalid BREBO workflow');
\Brebo\Office\Workflow\OfficeWorkflowBlueprintValidator::validate($definition);
$compiled = \Brebo\Office\Workflow\OfficeWorkflowBlueprintCompiler::compile($definition);
foreach (['direct_order','accepted_to_project','crm_to_inspection'] as $edge) {
    $found = false;
    foreach ($compiled['transitions'] as $transitions) {
        if (isset($transitions[$edge])) $found = true;
    }
    if (!$found) throw new RuntimeException('Missing required route: ' . $edge);
}
echo "BREBO workflow configuration OK\n";
