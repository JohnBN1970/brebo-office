<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$public=$root.'/public/';
foreach (['index.php','../deployment/hostinger/office-test-index.php'] as $router) {
 $source=file_get_contents($public.$router);
 foreach ([
  "'/office-test/crm/aanvragen/board.js' => 'office-crm-kanban-drag.php'",
  "'/office-test/crm/board.css' => 'office-crm-shared-board-style.php'",
  "'/office-test/crm/verkoopkansen/drag.js' => 'office-crm-kanban-drag.php'",
 ] as $route) if (!str_contains($source,$route)) throw new RuntimeException('Missing route '.$route.' in '.$router);
}
$script=file_get_contents($public.'office-crm-shared-board.js');
foreach (['lead-board','crm-kanban-drag','lead_id','URLSearchParams','response.redirected','window.location.reload()','crm-board-stage','crm-board-search'] as $part)
 if (!str_contains($script,$part)) throw new RuntimeException('Missing shared board behavior: '.$part);
$asset=file_get_contents($public.'office-crm-kanban-drag.php');
if (!str_contains($asset,"office-crm-shared-board.js")) throw new RuntimeException('Shared script not served');
$leads=file_get_contents($public.'crm-new-requests.php');
$deals=file_get_contents($public.'office-crm-opportunities.php');
foreach ([$leads,$deals] as $page)
 if (!str_contains($page,'/crm/board.css')) throw new RuntimeException('Shared CSS missing');
if (!str_contains($leads,'/crm/aanvragen/board.js')) throw new RuntimeException('Lead script missing');
if (!str_contains($deals,'/crm/verkoopkansen/drag.js')) throw new RuntimeException('Deal script missing');
echo "CRM_SHARED_BOARD_CONTRACT_OK\n";
