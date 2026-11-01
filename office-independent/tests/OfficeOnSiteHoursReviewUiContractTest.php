<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$endpoint = file_get_contents($root . '/public/office-onsite-hours-review.php');
$screen = file_get_contents($root . '/public/office-projects.php');
foreach ([
  [$router, "'/office-test/inzet/onsite/uren-beoordelen' => 'office-onsite-hours-review.php'"],
  [$endpoint, "REQUEST_METHOD"],
  [$endpoint, "CONTENT_LENGTH"],
  [$endpoint, "413"],
  [$endpoint, "OfficeInzetSessionCsrf::verify"],
  [$endpoint, "OnSiteHoursReviewRequest::parse"],
  [$endpoint, "OnSiteHoursReviewRepository"],
  [$endpoint, "OFFICE_TEST_ONSITE_HOURS_REVIEW_ENABLED"],
  [$screen, "name=\"csrf_token\""],
  [$screen, "name=\"request_key\""],
  [$screen, "value=\"approve\""],
  [$screen, "value=\"reject\""],
  [$screen, "canManage("],
] as [$haystack, $needle]) {
  if (!str_contains($haystack, $needle)) throw new RuntimeException('Missing hours review contract: ' . $needle);
}
echo "OfficeOnSiteHoursReviewUiContractTest OK\n";
