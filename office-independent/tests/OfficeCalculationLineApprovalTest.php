<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalculationLineApproval.php';
use Brebo\Office\Calculation\OfficeCalculationLineApproval as Approval;
$fingerprint=hash('sha256','line version one');
$reviewed=Approval::review(['status'=>'draft'],17,$fingerprint,'Hoeveelheden gecontroleerd');
if ($reviewed['status']!=='reviewed') throw new RuntimeException('review_failed');
foreach ([[17,true],[18,false]] as [$actor,$permission]) {
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
echo "Line review, head-sales release, four-eyes and revision invalidation OK\n";
