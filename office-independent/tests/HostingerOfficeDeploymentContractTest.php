<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/deployment/hostinger';
$router = file_get_contents($root . '/index.php');
$rules = file_get_contents($root . '/.htaccess');
if ($router === false || $rules === false) {
  throw new RuntimeException('Hostinger deployment entrypoint missing.');
}
foreach ([
  '/office/inloggen' => 'office-login.php',
  '/office/uitloggen' => 'office-logout.php',
  '/office/crm/aanvragen' => 'crm-new-requests.php',
  '/office/crm/aanvragen/detail' => 'crm-new-requests.php',
] as $route => $target) {
  if (!str_contains($router, "'" . $route . "' => '" . $target . "'")) {
    throw new RuntimeException('Missing approved Office route: ' . $route);
  }
}
foreach (['calc-start.php', 'calc-project-context.php', 'calculation-context$#D', 'OFFICE_PRIVATE_ROOT', 'realpath(', 'http_response_code(404)', 'http_response_code(503)'] as $required) {
  if (!str_contains($router, $required)) {
    throw new RuntimeException('Router safety requirement missing: ' . $required);
  }
}
foreach (['RewriteEngine On', 'DirectoryIndex index.php', 'Require all denied', 'RewriteRule ^calc/start$ index.php [END,QSA]', 'RewriteRule ^api/workbench/v1/projects/[1-9][0-9]*/calculation-context$', 'RewriteRule ^office/inloggen/', 'RewriteRule ^office/uitloggen/', 'RewriteRule ^office/crm/aanvragen/'] as $required) {
  if (!str_contains($rules, $required)) {
    throw new RuntimeException('Hostinger rewrite protection missing: ' . $required);
  }
}
if (str_contains($router, 'eval(') || str_contains($router, '$_GET[')) {
  throw new RuntimeException('Dynamic router execution or user-selected file paths are forbidden.');
}
// Keep the deployed query-router compatibility covered by CI.
foreach ([
  "'home' => '/home'",
  "'inloggen' => '/inloggen'",
  "'uitloggen' => '/uitloggen'",
  "'crm/aanvragen' => '/crm/aanvragen'",
  "'calc-werkbank' => '/calc-werkbank'",
  "'/home' => 'office-home.php'",
  "filter_input(INPUT_GET, 'office_route'",
  "office_private/config.php",
] as $required) {
  if (!str_contains($router, $required)) {
    throw new RuntimeException('Legacy Hostinger Office routing requirement missing: ' . $required);
  }
}
$public = dirname(__DIR__) . '/public';
$login = file_get_contents($public . '/office-login.php');
$home = file_get_contents($public . '/office-home.php');
$logout = file_get_contents($public . '/office-logout.php');
$workbench = file_get_contents($public . '/calc-workbench.php');
$form = file_get_contents(dirname(__DIR__) . '/src/Intake/WebsiteOfficeLoginFormRenderer.php');
if ($login === false || $home === false || $logout === false || $form === false || $workbench === false) {
  throw new RuntimeException('Office navigation endpoint missing.');
}
foreach ([
  [$login, '/index.php?office_route=home'],
  [$home, '/index.php?office_route=crm/aanvragen'],
  [$home, 'method="post"'],
  [$home, '/index.php?office_route=uitloggen'],
  [$home, '/index.php?office_route=calc-werkbank'],
  [$home, "['Calculatie', \$calcUrl, \$calcEnabled]"],
  [$home, 'Referrer-Policy: same-origin'],
  [$home, "getenv('OFFICE_RECENT_WORK_ENABLED') === 'enabled'"],
  [$home, 'PdoRecentWorkRepository.php'],
  [$home, 'listForOperator($operator->operatorId)'],
  [$home, "getenv('OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED') === 'enabled'"],
  [$router, "\$_SERVER['OFFICE_QUERY_ROUTER']"],
  [$workbench, '/index.php?office_route=inloggen'],
  [$workbench, 'name="start_mode" value="project_context"'],
  [$workbench, 'name="start_mode" value="standalone"'],
  [$workbench, 'project.value = ""; calc.value = "";'],
  [$workbench, 'project.required = !standalone; calc.required = !standalone;'],
  [$workbench, 'disabled = standalone'],
  [$workbench, 'if (form.elements.namedItem("start_mode").value === "standalone") return;'],
  [$logout, '/index.php?office_route=inloggen'],
  [$form, '/index.php?office_route=inloggen'],
] as [$content, $required]) {
  if (!str_contains($content, $required)) {
    throw new RuntimeException('Office query-router navigation contract missing: ' . $required);
  }
}
// CRM validates the canonical path itself: query-route forwarding must normalize it.
if (!str_contains($router, "if (\$path === '/crm/aanvragen' || \$path === '/crm/aanvragen/detail')") ||
  !str_contains($router, "\$_SERVER['REQUEST_URI'] = '/office' . \$path;")) {
  throw new RuntimeException('Legacy CRM query route does not normalize the canonical handler path.');
}
echo "Hostinger Office release routing contract passed.\\n";

require_once __DIR__ . '/RecentWorkEventTest.php';
