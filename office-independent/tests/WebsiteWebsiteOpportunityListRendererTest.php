<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteWebsiteOpportunityListRenderer;

require_once __DIR__ . '/../src/Intake/WebsiteWebsiteOpportunityListRenderer.php';

$renderer = new WebsiteWebsiteOpportunityListRenderer();
$empty = $renderer->render([]);
if (!str_contains($empty, 'Er zijn geen aanvragen op deze pagina.') || !str_contains($empty, 'lang="nl"')) {
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
$paged = $renderer->render(array_fill(0, 50, ['id' => 3, 'title' => 'Aanvraag']), 2, true);
if (!str_contains($paged, 'page=1') || !str_contains($paged, 'page=3') || !str_contains($paged, 'Pagina 2')) {
  throw new RuntimeException('CRM pagination navigation is missing.');
}
$lastPage = $renderer->render(array_fill(0, 50, ['id' => 3, 'title' => 'Aanvraag']), 2, false);
if (str_contains($lastPage, 'page=3')) {
  throw new RuntimeException('CRM last page incorrectly offers a next page.');
}
$emptySecondPage = $renderer->render([], 2);
if (!str_contains($emptySecondPage, 'page=1') || !str_contains($emptySecondPage, 'Vorige pagina')) {
  throw new RuntimeException('Empty CRM results page has no way back.');
}
echo "Independent CRM overview rendering checks passed.\n";
