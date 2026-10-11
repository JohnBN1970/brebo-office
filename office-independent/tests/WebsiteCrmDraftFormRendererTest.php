<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftFormRenderer;

require_once __DIR__ . '/../src/Intake/WebsiteCrmDraftFormRenderer.php';

$form = new WebsiteCrmDraftFormRenderer();
$html = $form->render(['legacy_node_id' => 42, 'title' => '<script>bad()</script>', 'stage' => 'Lead'], null, str_repeat('a', 64));
if (!str_contains($html, '&lt;script&gt;bad()&lt;/script&gt;')
  || str_contains($html, '<script>bad()</script>')
  || !str_contains($html, 'name="csrf_token"')
  || !str_contains($html, 'name="version" value="0"')
  || !str_contains($html, 'method="post"')) {
  throw new RuntimeException('CRM draft form escaping or write safeguards missing.');
}
$html = $form->render(['legacy_node_id' => 42, 'title' => 'Original', 'stage' => 'Lead'], ['title' => 'Updated', 'stage' => 'Qualified', 'version' => 3], str_repeat('b', 64));
if (!str_contains($html, 'name="version" value="3"') || !str_contains($html, 'value="Updated"')) {
  throw new RuntimeException('CRM draft form did not use the saved draft.');
}
echo "CRM draft form rendering checks passed.\n";
