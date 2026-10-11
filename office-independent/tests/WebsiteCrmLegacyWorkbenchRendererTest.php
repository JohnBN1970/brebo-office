<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyReadRepository;
use Brebo\Office\Intake\WebsiteCrmLegacyWorkbenchRenderer;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyReadRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyWorkbenchRenderer.php';

$db = new PDO(getenv('OFFICE_INTAKE_TEST_DSN'), getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE IF NOT EXISTS office_crm_legacy_opportunity (legacy_node_id BIGINT UNSIGNED PRIMARY KEY,title VARCHAR(255),stage VARCHAR(80),owner_uid BIGINT,website_request_id CHAR(36),source_json LONGTEXT)');
$db->exec('CREATE TABLE IF NOT EXISTS office_crm_legacy_party (legacy_node_id BIGINT UNSIGNED,party_type VARCHAR(32),title VARCHAR(255),source_json LONGTEXT,PRIMARY KEY(party_type,legacy_node_id))');
$db->exec('DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987654');
$db->prepare('INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (987654,:title,\'Lead\',1,NULL,\'{}\')')->execute(['title' => '<script>alert(1)</script>']);
$html = (new WebsiteCrmLegacyWorkbenchRenderer())->render(new WebsiteCrmLegacyReadRepository($db));
if (!str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
  || str_contains($html, '<script>alert(1)</script>')
  || !str_contains($html, '/brebo-internal/crm/migration/detail?id=987654')
  || !str_contains($html, 'Contactpersonen') || !str_contains($html, 'Organisaties')) {
  throw new RuntimeException('Standalone CRM workbench HTML is incomplete or unsafe.');
}
$db->exec('DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987654');
echo "Standalone CRM workbench rendering checks passed.\n";
