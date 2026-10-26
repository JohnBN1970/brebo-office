<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteApprovalProjection.php';

use Brebo\Office\Inzet\OnSiteApprovalProjection;

$in = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$out = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$other = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$session = ['person_id'=>'7','approval_status'=>'approved',
  'approved_at'=>'2026-10-26T10:00:00Z','in_event_id'=>$in,'out_event_id'=>$out];
$expected = [$in=>'2026-10-26T10:00:00Z', $out=>'2026-10-26T10:00:00Z'];
if (OnSiteApprovalProjection::forEmployee([$session], '7') !== $expected) {
  throw new RuntimeException('Approved complete session must expose both event timestamps');
}
if (OnSiteApprovalProjection::forEmployee([$session], '8') !== []) {
  throw new RuntimeException('Employee isolation failed');
}
foreach (['pending','rejected'] as $status) {
  if (OnSiteApprovalProjection::forEmployee([array_merge($session, ['approval_status'=>$status])], '7') !== []) {
    throw new RuntimeException('Unapproved sessions must never be archived');
  }
}
if (OnSiteApprovalProjection::forEmployee([array_merge($session, ['approved_at'=>null])], '7') !== []) {
  throw new RuntimeException('Missing Office approval timestamp must fail closed');
}
if (OnSiteApprovalProjection::forEmployee([array_merge($session, ['out_event_id'=>$other, 'person_id'=>'8'])], '7') !== []) {
  throw new RuntimeException('Cross-employee session must be excluded');
}
echo "OnSite approval projection checks passed\\n";
