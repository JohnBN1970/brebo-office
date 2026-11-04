<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/public/office-crm-relations.php');
$router=file_get_contents($root.'/deployment/hostinger/office-test-index.php');
$nav=file_get_contents($root.'/src/Intake/OfficeNavigation.php');
foreach ([
 'schema gate'=>str_contains($page,"OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED"),
 'session provider'=>str_contains($page,'WebsiteOfficeCrmCookieSessionProvider'),
 'active operator'=>str_contains($page,'office_operator_accounts'),
 'prepared query'=>str_contains($page,'$db->prepare($sql)'),
 'escaped output'=>str_contains($page,'htmlspecialchars'),
 'manager validity'=>str_contains($page,'rep.valid_from') && str_contains($page,'rep.valid_until'),
 'role filter'=>str_contains($page,'filter_role.relation_id=r.id'),
 'router'=>str_contains($router,"'/office-test/crm/relaties' => 'office-crm-relations.php'"),
 'navigation'=>str_contains($nav,"'/office-test/crm/relaties'"),
] as $label=>$ok) if (!$ok) throw new RuntimeException($label.' failed');
echo "CRM relations HTTP wiring OK\n";
