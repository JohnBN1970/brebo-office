<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$lead = file_get_contents($root.'/public/crm-new-requests.php');
$router = file_get_contents($root.'/public/index.php');
$hostinger = file_get_contents($root.'/deployment/hostinger/office-test-index.php');
$style = file_get_contents($root.'/public/css/office-history-panel.css');
if ($lead === false || $router === false || $hostinger === false || $style === false) {
    throw new RuntimeException('Missing Office history integration sources');
}
foreach ([$router, $hostinger] as $routes) {
    if (!str_contains($routes, "'/office-test/css/office-history-panel.css' => 'office-history-panel-style.php'")) {
        throw new RuntimeException('History CSS route missing');
    }
}
foreach ([
    'FROM office_crm_lead_events WHERE lead_id=?',
    'ORDER BY created_at DESC,id DESC LIMIT 100',
    'OfficeHistoryPanel::render($leadHistory)',
    'office-lead-detail-layout',
    'office-lead-detail-main',
] as $needle) {
    if (!str_contains($lead, $needle)) {
        throw new RuntimeException('Missing lead history integration: '.$needle);
    }
}
if (!str_contains($style, '.office-lead-detail-layout') || !str_contains($style, '.office-history-disclosure:not([open])')) {
    throw new RuntimeException('Missing responsive or collapsed history styles');
}
echo "Office history integration contract OK\n";
