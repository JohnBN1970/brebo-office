<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$api = file_get_contents($root . '/public/office-workflow-draft-api.php');
$ui = file_get_contents($root . '/public/office-workflow-preview.php');
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
if (!is_string($api) || !is_string($ui) || !is_string($router)) {
    throw new RuntimeException('Workflow editor files unavailable');
}
foreach ([
    'OFFICE_WORKFLOW_EDITOR_ENABLED', 'OFFICE_WORKFLOW_SCHEMA_VERIFIED',
    'OFFICE_WORKFLOW_EDITOR_OPERATOR_IDS', 'OFFICE_WORKFLOW_TENANT_ID',
    'OFFICE_WORKFLOW_PUBLISH_ENABLED', 'workflow_csrf', 'HTTP_ORIGIN',
    'OfficeWorkflowDraftReader', 'OfficeWorkflowDraftRepository',
    'OfficeWorkflowPublicationRepository', 'expected_sha256',
] as $gate) {
    if (!str_contains($api, $gate)) {
        throw new RuntimeException('Missing workflow API guard: ' . $gate);
    }
}
foreach (['load-draft', 'save-draft', 'publish-draft', 'expected_sha256', 'csrf'] as $binding) {
    if (!str_contains($ui, $binding)) {
        throw new RuntimeException('Missing editor binding: ' . $binding);
    }
}
if (!str_contains($router, '/office-test/installatie/workflows/api')
    || !str_contains($router, 'office-workflow-draft-api.php')) {
    throw new RuntimeException('Workflow API route missing');
}
echo "Workflow editor wiring contract OK\n";
