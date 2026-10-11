<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftRouteDispatcher;
use Brebo\Office\Intake\WebsiteCrmDraftHttpController;

require_once __DIR__ . '/../src/Intake/WebsiteCrmDraftRouteDispatcher.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmDraftHttpController.php';

$reflection = new ReflectionClass(WebsiteCrmDraftHttpController::class);
$controller = $reflection->newInstanceWithoutConstructor();
$disabled = new WebsiteCrmDraftRouteDispatcher($controller);
foreach ([
  ['GET','/brebo-internal/crm/migration/draft'],
  ['POST','/brebo-internal/crm/migration/draft'],
] as [$method,$path]) {
  if ($disabled->dispatch($method,$path,['id'=>'42'],[],100000)['status'] !== 404) {
    throw new RuntimeException('CRM write route enabled without explicit approval.');
  }
}
$enabled = new WebsiteCrmDraftRouteDispatcher($controller, true);
if ($enabled->dispatch('GET','/brebo-internal/crm/migration/draft',['id'=>'-1'],[],100000)['status'] !== 400
  || $enabled->dispatch('DELETE','/brebo-internal/crm/migration/draft',[],[],100000)['status'] !== 404
  || $enabled->dispatch('POST','/brebo-internal/crm/migration/other',[],[],100000)['status'] !== 404) {
  throw new RuntimeException('CRM draft route validation failed.');
}
echo "CRM draft route dispatcher checks passed.\n";
