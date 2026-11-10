<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/public/office-personal-dashboard.php');
if (!is_string($source)) throw new RuntimeException('Dashboard entrypoint missing');
foreach ([
  "REQUEST_METHOD", "OFFICE_BASE_PATH", "OFFICE_PERSONAL_DASHBOARD_ENABLED",
  "OFFICE_CRM_SESSION_MODE", "OFFICE_PUBLIC_HOST", "currentOperator()",
  "office_operator_accounts", "active = 1", "Cache-Control", "Content-Security-Policy",
  "OFFICE_INTAKE_PDO_DSN",
] as $required) {
  if (!str_contains($source, $required)) throw new RuntimeException('Missing dashboard security gate: '.$required);
}
if (str_contains($source, "\$_GET['operator_id']") || str_contains($source, "\$_POST['operator_id']")) {
  throw new RuntimeException('Browser-supplied operator identity is prohibited');
}
echo "Personal dashboard route security contract passed\n";
