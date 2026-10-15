<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyReadRepository;
use Brebo\Office\Intake\WebsiteCrmLegacyDetailRenderer;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyReadRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyDetailRenderer.php';

$db = new PDO(getenv('OFFICE_INTAKE_TEST_DSN'), getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$read = new WebsiteCrmLegacyReadRepository($db);
$renderer = new WebsiteCrmLegacyDetailRenderer();
if ($renderer->render($read, 999999999) !== null) {
  throw new RuntimeException('Missing CRM opportunity should not render.');
}
$db->prepare('INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (987655,:title,\'Lead\',1,NULL,\'{}\')')->execute(['title' => '<img src=x onerror=alert(1)>']);
$html = $renderer->render($read, 987655);
if (!is_string($html) || !str_contains($html, '&lt;img src=x onerror=alert(1)&gt;')
  || str_contains($html, '<img src=x onerror=alert(1)>') || !str_contains($html, 'Fasehistorie')) {
  throw new RuntimeException('CRM opportunity detail rendering is unsafe or incomplete.');
}
$db->exec('DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987655');
echo "Independent CRM opportunity detail checks passed.\n";
