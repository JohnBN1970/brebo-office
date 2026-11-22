<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeCl204Projectopname.php';
use Brebo\Office\Process\OfficeCl204Projectopname as CL204;
if (CL204::CODE !== 'CL-204' || count(CL204::controls()) !== 17) throw new RuntimeException('CL-204 controls missing');
if (CL204::validateDraft(['controls'=>['maatvoering'=>['status'=>'niet_van_toepassing']],'disciplines'=>['kozijnen','schilderwerk'],'observations'=>[['observation'=>'vochtplek','evidence_refs'=>[]]]]) !== []) throw new RuntimeException('Valid CL-204 draft rejected');
if (!isset(CL204::validateDraft(['controls'=>['maatvoering'=>['status'=>'invalid']]])['controls.maatvoering'])) throw new RuntimeException('Invalid status accepted');
if (count(CL204::releaseStatuses()) !== 6) throw new RuntimeException('CL-204 release statuses incomplete');
if (!isset(CL204::validateDraft(['release_status'=>'V7'])['release_status'])) throw new RuntimeException('Invalid release status accepted');
if (!isset(CL204::validateDraft(['controls'=>'bad'])['controls'])) throw new RuntimeException('Invalid control list accepted');
if (!isset(CL204::validateDraft(['observations'=>[['invented'=>'x']]])['observations.0'])) throw new RuntimeException('Unknown observation field accepted');
if (!isset(CL204::validateDraft(['observations'=>[['evidence_refs'=>[42]]]])['observations.0'])) throw new RuntimeException('Invalid evidence reference accepted');
echo "CL-204 draft contract OK\n";
