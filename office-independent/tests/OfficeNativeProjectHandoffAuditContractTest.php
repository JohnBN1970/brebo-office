<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/deployment/hostinger/audit-native-project-handoff-preflight.php');
if (!is_string($source)) throw new RuntimeException('Missing native project handoff audit');
foreach ([
    "PHP_SAPI !== 'cli'",
    'OFFICE_HANDOFF_PDO_BOOTSTRAP',
    'OfficeNativeProjectHandoffPreflight::inspect($db)',
    "json_encode(\$result",
    "exit(\$result['schema_ready'] ? 0 : 2)",
] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Missing audit safety contract: ' . $required);
}
if (preg_match('/\\b(INSERT|UPDATE|DELETE|ALTER|DROP|CREATE)\\b/i', $source)) {
    throw new RuntimeException('Audit must not mutate the database');
}
echo "NATIVE_PROJECT_HANDOFF_AUDIT_CONTRACT=PASS\n";
