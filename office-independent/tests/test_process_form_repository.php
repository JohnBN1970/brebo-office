<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormRepository.php';
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormCatalog.php';
use Brebo\Office\Process\OfficeProcessFormRepository as Repository;
$ref = new ReflectionClass(Repository::class);
foreach (['create','load','revise'] as $method) {
    if (!$ref->hasMethod($method) || !$ref->getMethod($method)->isPublic()) {
        throw new RuntimeException('Missing repository API: '.$method);
    }
}
$source = file_get_contents(dirname(__DIR__).'/src/Process/OfficeProcessFormRepository.php');
foreach (['beginTransaction()', 'rollBack()', 'revision=revision+1', "status='concept'", 'appendEvent(', 'OfficeProcessFormCatalog::requireCode'] as $contract) {
    if (!str_contains($source,$contract)) throw new RuntimeException('Missing repository invariant: '.$contract);
}
echo "Process form repository contracts OK\n";
