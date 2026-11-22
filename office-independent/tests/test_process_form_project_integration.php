<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$project=file_get_contents($root.'/public/office-project.php');
$catalog=file_get_contents($root.'/public/office-process-forms.php');
$preflight=file_get_contents($root.'/src/Process/OfficeProcessFormSchemaPreflight.php');
foreach ([
    'OFFICE_TEST_PROCESS_FORMS_CATALOG_ENABLED',
    "'/procesformulieren?project_id='",
] as $fragment) {
    if (!str_contains($project,$fragment)) throw new RuntimeException('Missing project navigation gate: '.$fragment);
}
foreach ([
    'office_project_memberships',
    'm.operator_id=?',
    "m.permission IN ('read','manage')",
    "a.active=1",
    "OfficeProcessFormContext::accepts",
    "http_response_code(403)",
] as $fragment) {
    if (!str_contains($catalog,$fragment)) throw new RuntimeException('Missing project scope gate: '.$fragment);
}
foreach ([
    'information_schema.KEY_COLUMN_USAGE',
    'fk_process_form_event_instance',
    'fk_process_form_evidence_instance',
    'uq_process_form_event_revision',
] as $fragment) {
    if (!str_contains($preflight,$fragment)) throw new RuntimeException('Missing schema integrity check: '.$fragment);
}
echo "Process form project integration and schema gate OK\n";
