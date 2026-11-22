<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormRepository.php';
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormAuthorization.php';
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormService.php';
use Brebo\Office\Process\OfficeProcessFormService as S;
$reflection=new ReflectionClass(S::class);
foreach (['create','load','revise','transition'] as $method) {
    if (!$reflection->hasMethod($method) || !$reflection->getMethod($method)->isPublic()) throw new RuntimeException('Missing service method '.$method);
}
$source=file_get_contents(dirname(__DIR__).'/src/Process/OfficeProcessFormService.php');
foreach (['OfficeProcessFormAuthorization::assertPermitted','if (!$csrfValid)','requireForm($id)',"fourEyesPolicyResolver", "is_bool($fourEyes)", "transition($id,$expectedRevision,$action,$actor,$fourEyes,true,$reason)"] as $invariant) {
    if (!str_contains($source,$invariant)) throw new RuntimeException('Missing service boundary '.$invariant);
}
$transition=$reflection->getMethod('transition');
if ($transition->getNumberOfParameters() !== 6) throw new RuntimeException('Client-controlled four-eyes flag must not be accepted');
echo "Process form application boundary contracts OK\n";
