<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeFr201LeadReadiness.php';
use Brebo\Office\Process\OfficeFr201LeadReadiness as Readiness;
use Brebo\Office\Process\OfficeFr201Intake as Intake;

$lead = ['source_label'=>'Website','source_detail'=>'webform','contact_name'=>'Jan',
    'contact_email'=>'jan@example.nl','description'=>'Kozijnen vervangen'];
$result = Readiness::assess($lead);
if ($result['complete'] || !in_array('contract_party',$result['missing'],true)
    || !in_array('relation_id',$result['missing'],true)) {
    throw new RuntimeException('Unqualified lead must not be treated as complete intake');
}
if ($result['known']['channel'] !== 'Website' || $result['known']['customer_question'] !== 'Kozijnen vervangen') {
    throw new RuntimeException('Existing lead data lost');
}
$extra = [];
foreach (Intake::fields() as $key => $definition) if ($definition['required']) $extra[$key]='bevestigd';
$extra['intake_id']='INT-2026-0001';
$extra['contact_email']='jan@example.nl';
$extra['owner_user_id']='1';
$lead['relation_id']='123';
$lead['owner_user_id']='1';
$lead['received_at']='2026-11-21 10:00:00';
$result = Readiness::assess($lead,$extra);
if (!$result['complete'] || $result['missing'] !== []) throw new RuntimeException('Completed intake still missing fields');
echo "FR-201 lead readiness OK\n";
