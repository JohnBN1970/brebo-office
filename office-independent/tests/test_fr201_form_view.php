<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeFr201FormView.php';
use Brebo\Office\Process\OfficeFr201FormView as View;
if (View::render(1,[],0,'','csrf',true) !== '') throw new RuntimeException('Action required');
if (View::render(1,[],0,'/office-test/crm/aanvragen/detail','',true) !== '') throw new RuntimeException('CSRF required');
$readOnly=View::render(1,[],0,'/office-test/crm/aanvragen/detail','csrf',false);
if (str_contains($readOnly,'<form')) throw new RuntimeException('Read-only form editable');
$html=View::render(12,['contract_party'=>'<script>alert(1)</script>'],2,'/office-test/crm/aanvragen/detail','csrf',true);
if (str_contains($html,'<script>') || !str_contains($html,'&lt;script&gt;')) throw new RuntimeException('XSS escape failed');
foreach (['expected_revision','csrf_token','change_reason','fr201_action'] as $name) {
    if (!str_contains($html,'name="'.$name.'"')) throw new RuntimeException('Missing '.$name);
}
if (str_contains($html,'name="fr201[contact_email]"')) throw new RuntimeException('Master data must not be duplicated');
echo "FR-201 form view OK\n";
