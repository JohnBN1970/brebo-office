<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/WebsiteWebsiteOpportunityListRenderer.php';
require_once dirname(__DIR__) . '/src/Intake/WebsiteWebsiteOpportunityDetailRenderer.php';

use Brebo\Office\Intake\WebsiteWebsiteOpportunityListRenderer;
use Brebo\Office\Intake\WebsiteWebsiteOpportunityDetailRenderer;

function assertCrmMount(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$rows = [['id' => 12, 'title' => 'Testaanvraag', 'created_at' => '', 'source' => '', 'stage' => '', 'next_action' => '']];
$list = new WebsiteWebsiteOpportunityListRenderer();
$test = $list->render($rows, 2, true, '/office-test');
assertCrmMount(str_contains($test, 'href="/office-test/crm/aanvragen/detail?id=12"'), 'Test detail URL wrong');
assertCrmMount(str_contains($test, 'href="/office-test/crm/aanvragen?page=1"'), 'Test previous URL wrong');
assertCrmMount(str_contains($test, 'href="/office-test/crm/aanvragen?page=3"'), 'Test next URL wrong');
assertCrmMount(str_contains($test, 'action="/office-test/uitloggen"'), 'Test logout URL wrong');
assertCrmMount(!str_contains($test, 'href="/office/crm/'), 'Production URL leaked in test navigation');
$empty = $list->render([], 2, false, '/office-test');
assertCrmMount(str_contains($empty, '/office-test/crm/aanvragen?page=1'), 'Empty page URL wrong');
$prod = $list->render($rows);
assertCrmMount(str_contains($prod, 'href="/office/crm/aanvragen/detail?id=12"'), 'Production URL changed');
$detail = (new WebsiteWebsiteOpportunityDetailRenderer())->render($rows[0], '/office-test');
assertCrmMount(str_contains($detail, 'href="/office-test/crm/aanvragen"'), 'Detail back URL wrong');
echo "CRM mount navigation checks passed.\n";
