<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteWebsiteOpportunityListRenderer;

require_once __DIR__ . '/../src/Intake/WebsiteWebsiteOpportunityListRenderer.php';

$renderer = new WebsiteWebsiteOpportunityListRenderer();
$empty = $renderer->render([]);
if (!str_contains($empty, 'Er zijn nog geen aanvragen ontvangen.') || !str_contains($empty, 'lang="nl"')) {
  throw new RuntimeException('CRM empty state missing.');
}
$html = $renderer->render([[
  'title' => '<script>alert(1)</script>',
  'created_at' => '2026-10-12 09:00:00',
  'source' => 'europakozijn',
  'stage' => 'lead',
  'next_action' => 'Documenten beoordelen',
  'requires_review' => 1,
]]);
if (!str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
  || str_contains($html, '<script>alert(1)</script>')
  || !str_contains($html, 'Te beoordelen')
  || !str_contains($html, 'aria-label="Overzicht nieuwe aanvragen"')
  || !str_contains($html, 'Documenten beoordelen')) {
  throw new RuntimeException('CRM overview escaping or accessibility check failed.');
}
echo "Independent CRM overview rendering checks passed.\n";
