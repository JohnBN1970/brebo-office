<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeProjectTrafficLight.php';
use Brebo\Office\Project\OfficeProjectTrafficLight;
function verifyLight(bool $pass): void { if (!$pass) { fwrite(STDERR, "TRAFFIC_LIGHT_TEST_FAILED\n"); exit(1); } }
verifyLight(OfficeProjectTrafficLight::assess(['status'=>'concept'])['color'] === 'gray');
verifyLight(OfficeProjectTrafficLight::assess(['status'=>'concept','plan_items'=>[['status'=>'ontrack']]])['color'] === 'green');
verifyLight(OfficeProjectTrafficLight::assess(['status'=>'concept','plan_items'=>[['status'=>'waiting']]])['color'] === 'orange');
verifyLight(OfficeProjectTrafficLight::assess(['status'=>'concept','plan_items'=>[['status'=>'critical','title'=>'Levering','critical_note'=>'Vertraging']]])['color'] === 'red');
verifyLight(OfficeProjectTrafficLight::assess(['status'=>'completed'])['color'] === 'gray');
$html = OfficeProjectTrafficLight::render(['plan_items'=>[['status'=>'critical','title'=>'<script>','critical_note'=>'Risico']]]);
verifyLight(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'));
echo "TRAFFIC_LIGHT_TEST_OK\n";
