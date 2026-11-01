<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteHoursReviewRequest.php';
use Brebo\Office\Inzet\OnSiteHoursReviewRequest;

$valid = [
  'project_id'=>'1','person_id'=>'2','in_event_id'=>'in-1','out_event_id'=>'out-1',
  'decision'=>'approve','corrected_seconds'=>'1800','reason'=>'',
  'request_key'=>str_repeat('a',64),
];
$parsed = OnSiteHoursReviewRequest::parse($valid);
if ($parsed['corrected_seconds'] !== 1800 || $parsed['reason'] !== null) throw new RuntimeException('Valid request failed.');
foreach ([
  ['decision'=>'delete'], ['corrected_seconds'=>'-1'], ['corrected_seconds'=>'86401'],
  ['corrected_seconds'=>'1.5'], ['project_id'=>'0'], ['in_event_id'=>'../../x'],
  ['request_key'=>'wrong'], ['reason'=>str_repeat('x',2001)],
  ['decision'=>'reject','corrected_seconds'=>'10'],
] as $change) {
  try {
    OnSiteHoursReviewRequest::parse(array_replace($valid, $change));
  } catch (InvalidArgumentException $expected) {
    continue;
  }
  throw new RuntimeException('Invalid review request accepted: '.json_encode($change));
}
echo "OnSiteHoursReviewRequestTest OK\n";
