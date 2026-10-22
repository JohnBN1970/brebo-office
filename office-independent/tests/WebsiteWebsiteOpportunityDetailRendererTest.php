<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteWebsiteOpportunityDetailRenderer;

require_once __DIR__ . '/../src/Intake/WebsiteWebsiteOpportunityDetailRenderer.php';

$html = (new WebsiteWebsiteOpportunityDetailRenderer())->render([
  'title' => '<script>alert(1)</script>',
  'requirement_text' => "Regel een\nRegel twee <img src=x onerror=alert(1)>",
  'next_action' => 'Aanvraag beoordelen',
  'stage' => 'lead',
  'requires_review' => 1,
  'request_id' => '8c3b5f21-71a1-4dcb-bc61-f8c1d00c9881',
]);
if (!str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
  || str_contains($html, '<script>alert(1)</script>')
  || !str_contains($html, 'Regel een<br')
  || !str_contains($html, '&lt;img src=x onerror=alert(1)&gt;')
  || !str_contains($html, 'Nog te beoordelen')
  || !str_contains($html, '/office/crm/aanvragen')) {
  throw new RuntimeException('Independent CRM detail HTML escaping or navigation failed.');
}
echo "Independent CRM detail rendering checks passed.\n";
