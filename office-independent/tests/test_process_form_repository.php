<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormRepository.php';
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormCatalog.php';
use Brebo\Office\Process\OfficeProcessFormRepository as Repository;
$ref = new ReflectionClass(Repository::class);
foreach (['create','load','revise','transition'] as $method) {
    if (!$ref->hasMethod($method) || !$ref->getMethod($method)->isPublic()) {
        throw new RuntimeException('Missing repository API: '.$method);
    }
}
$source = file_get_contents(dirname(__DIR__).'/src/Process/OfficeProcessFormRepository.php');
foreach (['beginTransaction()', 'rollBack()', 'revision=revision+1', "status='concept'", 'appendEvent(', 'OfficeProcessFormCatalog::requireCode'] as $contract) {
    if (!str_contains($source,$contract)) throw new RuntimeException('Missing repository invariant: '.$contract);
}
foreach (['FOR UPDATE', "event_type='submit'", 'OfficeProcessFormWorkflow::target', 'Concurrent form decision', 'Goedkeuring vereist een geregistreerde indiening'] as $contract) {
    if (!str_contains($source,$contract)) throw new RuntimeException('Missing transition invariant: '.$contract);
}
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormContext.php';
if (\Brebo\Office\Process\OfficeProcessFormContext::accepts('FR-201','project')
    || !\Brebo\Office\Process\OfficeProcessFormContext::accepts('FR-201','lead')) {
    throw new RuntimeException('Incorrect CRM intake context mapping');
}
foreach (['OfficeProcessFormContext::accepts($code,$contextType)','Formuliercode hoort niet bij deze modulecontext'] as $contract) {
    if (!str_contains($source,$contract)) throw new RuntimeException('Missing repository module context guard: '.$contract);
}
echo "Process form repository contracts OK\n";
