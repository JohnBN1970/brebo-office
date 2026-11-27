<?php
declare(strict_types=1);

/**
 * Static contract regression: fails when the Office-test report creation
 * path loses server-side authorization, CSRF, draft status or project scoping.
 * Does not replace an integration test against the isolated Office-test DB.
 */
$project = file_get_contents(__DIR__.'/../public/office-project.php');
$schema = file_get_contents(__DIR__.'/../sql/20261127_project_handover_reports.sql');
$migration = file_get_contents(__DIR__.'/../deployment/vps/handover-report-migrate.php');
if (!is_string($project) || !is_string($schema) || !is_string($migration)) {
    fwrite(STDERR, "HANDOVER_CONTRACT=FILES_MISSING\n"); exit(1);
}
$checks = [
    'project_read_acl' => '->canRead((int) $operator->operatorId, $projectId)',
    'project_manage_acl' => '|| !$canManage || $_POST[\'handover_action\'] !== \'create\'',
    'test_only' => '!\\Brebo\\Office\\Intake\\OfficeMount::isTest()',
    'csrf_cookie' => '$_COOKIE[\'office_project_csrf\']',
    'csrf_constant_time' => 'hash_equals($cookie, $csrf)',
    'server_project' => '$stmt->execute([$projectId, $title, $notes, (int)$operator->operatorId])',
    'draft_only' => "VALUES (?, ?, ?, 'draft', ?, CURRENT_TIMESTAMP)",
    'post_redirect_get' => '&handover_saved=1',
    'create_form' => 'name="handover_title"',
    'create_button' => 'Concept opslaan',
    'list_scoped' => 'WHERE project_id=? ORDER BY id DESC LIMIT 50',
];
foreach ($checks as $name => $needle) {
    if (!str_contains($project, $needle)) {
        fwrite(STDERR, "HANDOVER_CONTRACT=FAIL ".$name."\n"); exit(1);
    }
}
foreach ([
    'schema_project' => 'project_id BIGINT UNSIGNED NOT NULL',
    'schema_actor' => 'created_by_operator_id BIGINT UNSIGNED NOT NULL',
    'schema_draft' => "DEFAULT 'draft'",
] as $name => $needle) {
    if (!str_contains($schema, $needle)) {
        fwrite(STDERR, "HANDOVER_CONTRACT=FAIL ".$name."\n"); exit(1);
    }
}
foreach ([
    'isolated_db' => "brebo_office_test",
    'guarded_apply' => 'CONFIRM-OFFICE-TEST-HANDOVER',
    'plan_mode' => '--plan',
    'existing_schema_check' => 'EXISTING_SCHEMA_MISMATCH',
] as $name => $needle) {
    if (!str_contains($migration, $needle)) {
        fwrite(STDERR, "HANDOVER_CONTRACT=FAIL ".$name."\n"); exit(1);
    }
}
echo "HANDOVER_CONTRACT=PASS\n";
