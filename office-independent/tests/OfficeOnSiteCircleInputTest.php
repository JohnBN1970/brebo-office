<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeCircleInput.php';

use Brebo\Office\Inzet\OnSiteOfficeCircleInput;

$input = ['project_id'=>'17','zone_name'=>'Werkzone','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>150];
$valid = OnSiteOfficeCircleInput::forAuthorizedProject($input,'17');
if ($valid['project_id'] !== '17' || $valid['radius_metres'] !== 150.0) {
  throw new RuntimeException('Authorized Office circle rejected');
}
$rejected = false;
try {
  OnSiteOfficeCircleInput::forAuthorizedProject($input,'18');
} catch (InvalidArgumentException $exception) {
  $rejected = $exception->getMessage() === 'office_circle_project_not_authorized';
}
if (!$rejected) throw new RuntimeException('Client circle project override accepted');
$rejected = false;
try {
  OnSiteOfficeCircleInput::forAuthorizedProject($input,null);
} catch (InvalidArgumentException $exception) {
  $rejected = $exception->getMessage() === 'office_circle_project_not_authorized';
}
if (!$rejected) throw new RuntimeException('Missing Office session project accepted');
$input['zone_id']='99';
$rejected = false;
try {
  OnSiteOfficeCircleInput::forAuthorizedProject($input,'17');
} catch (InvalidArgumentException $exception) {
  $rejected = $exception->getMessage() === 'office_circle_client_identity';
}
if (!$rejected) throw new RuntimeException('Client-supplied zone ID accepted');
echo "Office circle project authorization boundary OK\n";
