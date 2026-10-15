<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOpportunityMapper;

require_once __DIR__ . '/../src/Intake/WebsiteOpportunityMapper.php';

$mapper = new WebsiteOpportunityMapper();
$id = 'aaaaaaaa-1111-4111-8111-111111111111';
$lead = $mapper->map($id, [
  'metadata' => ['project_name' => 'Renovatie 12 woningen'],
  'preliminary_scope' => [
    'summary' => 'Voorlopig 12 kozijnen',
    'items' => [[
      'reference' => 'K-01',
      'quantity' => ['value' => 12],
      'dimensions' => ['value' => ['width_mm' => 1200, 'height_mm' => 1500]],
      'material' => ['value' => 'hout'],
      'glass' => ['value' => 'HR++'],
      'state' => ['value' => 'te controleren'],
      'missing_fields' => ['kleur'],
    ]],
    'conflicts' => [['field' => 'maatvoering']],
  ],
]);
$required = ['Websiteproject: Renovatie 12 woningen', 'VOORLOPIGE MACHINESCOPE', 'K-01', 'aantal 12', '1200 x 1500 mm', 'materiaal hout', 'glas HR++', 'status te controleren', 'open: kleur', 'Open conflicten: 1.'];
foreach ($required as $part) {
  if (!str_contains($lead['requirement_text'], $part)) {
    throw new RuntimeException('Missing CRM scope context: ' . $part);
  }
}
if ($lead['stage'] !== 'Lead' || $lead['probability'] !== 10
  || $lead['active'] !== true || $lead['requires_review'] !== true
  || $lead['title'] !== 'Europakozijn - aanvraag [aaaaaaaa]') {
  throw new RuntimeException('CRM lead defaults changed.');
}
$fallback = $mapper->map($id, []);
if (!str_contains($fallback['requirement_text'], 'Nieuwe aanvraag')) {
  throw new RuntimeException('Fallback project label missing.');
}
$long = $mapper->map($id, ['metadata' => ['project_name' => str_repeat('a', 20000)]]);
if (mb_strlen($long['requirement_text']) > 10000) {
  throw new RuntimeException('CRM requirement exceeded maximum length.');
}
echo "Canonical CRM mapper checks passed.\n";
