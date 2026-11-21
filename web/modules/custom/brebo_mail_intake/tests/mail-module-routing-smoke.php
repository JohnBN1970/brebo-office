<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Service/MailModuleRoutingAdvisor.php';

use Drupal\brebo_mail_intake\Service\MailModuleRoutingAdvisor;

$advisor = new MailModuleRoutingAdvisor();
$cases = [
  ['financieel', 95.0, [], [], 'finance', 'review'],
  ['offerte', 95.0, [], ['organization_id' => 42], 'crm', 'suggested'],
  ['offerte', 95.0, [], [], 'crm', 'review'],
  ['planning', 95.0, ['project_id' => 12], [], 'projecten', 'suggested'],
  ['planning', 70.0, ['project_id' => 12], [], 'projecten', 'review'],
  ['overig', 95.0, [], [], 'email', 'review'],
  ['juridisch', 95.0, [], [], 'email', 'review'],
];

foreach ($cases as $index => [$classification, $confidence, $project, $crm, $module, $state]) {
  $result = $advisor->advise($classification, $confidence, [], $project, $crm);
  if ($result['module'] !== $module || $result['state'] !== $state) {
    throw new RuntimeException(sprintf('Routing case %d failed: %s', $index, json_encode($result)));
  }
}

echo "Mail module routing smoke tests passed (" . count($cases) . " cases).\n";
