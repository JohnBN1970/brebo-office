<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormFr201.php';
use Brebo\Office\Process\OfficeProcessFormFr201 as F;
if (F::VERSION!=='1.0' || count(F::FIELDS)!==17) throw new RuntimeException('FR-201 source schema mismatch');
$required=array_keys(array_filter(F::FIELDS,static fn(array $definition):bool=>$definition[2]));
if (count($required)!==14) throw new RuntimeException('FR-201 required count mismatch');
if (F::missingRequired([])!==$required) throw new RuntimeException('Missing mandatory fields not detected');
$data=array_fill_keys($required,'ingevuld');
if (F::missingRequired($data)!==[]) throw new RuntimeException('Valid FR-201 falsely rejected');
unset($data['klantvraag']);
if (F::missingRequired($data)!==['klantvraag']) throw new RuntimeException('Klantvraag requirement not enforced');
$repositorySource=file_get_contents(dirname(__DIR__).'/src/Process/OfficeProcessFormRepository.php');
foreach ([
    "if (\$action === 'submit')",
    'OfficeProcessFormFr201::missingRequired($data)',
    'FR-201 verplichte velden ontbreken',
    'FOR UPDATE',
] as $fragment) {
    if (!str_contains($repositorySource,$fragment)) {
        throw new RuntimeException('Missing atomic FR-201 submission check: '.$fragment);
    }
}
echo "FR-201 source form definition OK\n";
