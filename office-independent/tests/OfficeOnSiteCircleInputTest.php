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
// Keep Office circle validation aligned with the iPhone geofence maximum.
$atLimit=OnSiteOfficeCircleInput::forAuthorizedProject(array_replace($input,['radius_metres'=>1000]),'17');
if ($atLimit['radius_metres']!==1000.0) throw new RuntimeException('1000 metre zone rejected');
$tooLarge=false;
try {
  OnSiteOfficeCircleInput::forAuthorizedProject(array_replace($input,['radius_metres'=>1000.01]),'17');
} catch (InvalidArgumentException $exception) {
  $tooLarge=true;
}
if (!$tooLarge) throw new RuntimeException('Circle input accepted radius above 1000 metres');
$input['zone_id']='99';
$rejected = false;
try {
  OnSiteOfficeCircleInput::forAuthorizedProject($input,'17');
} catch (InvalidArgumentException $exception) {
  $rejected = $exception->getMessage() === 'office_circle_client_identity';
}
if (!$rejected) throw new RuntimeException('Client-supplied zone ID accepted');
echo "Office circle project authorization boundary OK\n";
