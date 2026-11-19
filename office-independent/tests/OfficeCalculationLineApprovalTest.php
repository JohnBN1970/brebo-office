<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalculationLineApproval.php';
use Brebo\Office\Calculation\OfficeCalculationLineApproval as Approval;
$fingerprint=hash('sha256','line version one');
$reviewed=Approval::review(['status'=>'draft','author_id'=>12],17,$fingerprint,'Hoeveelheden gecontroleerd',12,2,3);
if ($reviewed['status']!=='reviewed') throw new RuntimeException('review_failed');
foreach ([[17,true],[18,false],[12,true]] as [$actor,$permission]) {
  try {
    Approval::release($reviewed,$actor,$permission,$fingerprint);
    throw new RuntimeException('unauthorized_release_allowed');
  } catch (DomainException $expected) {}
}
$released=Approval::release($reviewed,18,true,$fingerprint);
if ($released['status']!=='released'||$released['released_by']!==18) throw new RuntimeException('release_failed');
$changed=Approval::invalidateAfterEdit($released,hash('sha256','line version two'));
if ($changed['status']!=='draft'||isset($changed['released_by'])) throw new RuntimeException('stale_approval_survived_edit');
try {
  Approval::release($reviewed,18,true,hash('sha256','line version two'));
  throw new RuntimeException('stale_fingerprint_released');
} catch (DomainException $expected) {}
foreach ([[12,2,3],[17,2,3],[18,3,3],[18,4,3]] as [$actor,$authorRank,$reviewerRank]) {
  try {
    Approval::review(['status'=>'draft','author_id'=>12],$actor,$fingerprint,'',12,$authorRank,$reviewerRank);
    throw new RuntimeException('invalid_review_hierarchy_accepted');
  } catch (DomainException $expected) {}
}
try {
  Approval::review(['status'=>'draft','author_id'=>12],18,$fingerprint);
  throw new RuntimeException('unverified_hierarchy_accepted');
} catch (DomainException $expected) {}
echo "Line review, head-sales release, four-eyes and revision invalidation OK\n";
