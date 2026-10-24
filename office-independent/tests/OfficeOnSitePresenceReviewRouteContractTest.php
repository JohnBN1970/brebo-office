<?php
declare(strict_types=1);
/**
 * Office-test review deployment contract: route, gate and Apache rewrite must
 * agree before any files are transferred to Hostinger.
 */
$base=dirname(__DIR__);
$router=file_get_contents($base.'/deployment/hostinger/office-test-index.php');
$page=file_get_contents($base.'/public/office-onsite-presence-review.php');
$map=file_get_contents($base.'/public/office-onsite-project-map.php');
if (!is_string($router) || !str_contains($router,
  "'/office-test/inzet/aanwezigheid' => 'office-onsite-presence-review.php'"))
  throw new RuntimeException('presence_review_route_missing');
if (!is_string($page)
  || !str_contains($page,"OFFICE_TEST_PRESENCE_REVIEW_ENABLED")
  || !str_contains($page,"OFFICE_ONSITE_SCHEMA_VERIFIED")
  || !str_contains($page,"OnSiteOfficeZoneProjectAccess")
  || !str_contains($page,"OnSitePresenceReviewRepository"))
  throw new RuntimeException('presence_review_access_gate_missing');
if (!is_string($map) || !str_contains($map,'/office-test/inzet/aanwezigheid?id='))
  throw new RuntimeException('presence_review_navigation_missing');
echo "Office-test presence review routing contract OK\n";
