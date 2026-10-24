<?php
declare(strict_types=1);

// Static regression checks for the Office dashboard wiring. No DB or secrets required.
$source = file_get_contents(__DIR__ . '/../public/office-home.php');
if ($source === false) { fwrite(STDERR, "Office home missing\n"); exit(1); }
$required = [
  "Relaties & CRM",
  "Projecten",
  "Calculatie & offertes",
  "Financiën",
  "Organisatie",
  "Communicatie",
  "Dashboard · Mijn werkplek",
  "Inzet",
  "Gebouwen",
  "Documenten",
  "Inkoop",
  "Overleg",
  "Mail",
  "canEditCrm controls edit actions",
  "OFFICE_RECENT_WORK_ACL_VERIFIED",
  "office_project_memberships",
  "COUNT(DISTINCT p.id)",
  "Project count unavailable",
  "is_numeric",
  "myProjectCount",
  "myRecentProjects",
  "recentProjectsUnavailable",
  "tijdelijk niet beschikbaar",
  "/projecten/detail?id=",
  "FILTER_VALIDATE_INT",
  "min_range",
  "Project #",
  "INNER JOIN office_project_memberships m ON m.project_id=p.id",
  '$projectsEnabled = $myProjectCount > 0;',
  "Mijn recente projecten",
  "ORDER BY p.id DESC LIMIT 5",
  "Mijn projecten",
  "availableCount",
  "module-status",
  'Do not reorder modules based on deployment flags.',
  "m.permission IN ('read','manage')",
  "a.active=1",
  'if (!$projectsEnabled)',
  '$projectsEnabled && $myProjectCount !== null',
  "OFFICE_TEST_INZET_IMPORT_REVIEWER_ID",
  "['Mail', null, false]",
  "OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED",
  "OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED",
  "OFFICE_TEST_CRM_ENABLED",
  "currentOperator()",
  'htmlspecialchars($label',
];
foreach ($required as $item) {
  if (!str_contains($source, $item)) {
    fwrite(STDERR, "Missing navigation/security marker: $item\n");
    exit(1);
  }
}
if (!str_contains($source, 'if ($active && is_string($url))')) {
  fwrite(STDERR, "Disabled module guard missing\n"); exit(1);
}
if (!str_contains($source, 'htmlspecialchars($url, ENT_QUOTES, \'UTF-8\')')) {
  fwrite(STDERR, "Module link escaping missing\n"); exit(1);
}
if (!str_contains($source, 'if ($operator === null)')) {
  fwrite(STDERR, "Session guard missing\\n"); exit(1);
}
echo "Office navigation static guards OK\\n";
