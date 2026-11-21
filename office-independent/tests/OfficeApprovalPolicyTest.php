<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Approval/OfficeApprovalPolicy.php';
use Brebo\Office\Approval\OfficeApprovalPolicy as P;
function reject(callable $f):void {try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Expected rejection');}
foreach(P::PROCESSES as $process){if(P::resolve($process,null)!==P::FOUR_EYES)throw new RuntimeException('Unsafe default');if(P::resolve($process,P::SINGLE)!==P::SINGLE)throw new RuntimeException('Wrong mode');}
reject(fn()=>P::resolve('unknown',P::SINGLE));
reject(fn()=>P::resolve('onsite_hours','disabled'));
reject(fn()=>P::assertReviewers(P::FOUR_EYES,'first',null));
reject(fn()=>P::assertReviewers(P::FOUR_EYES,'first','first'));
P::assertReviewers(P::FOUR_EYES,'first','second');
P::assertReviewers(P::SINGLE,'first',null);
echo "PASS\n";
