<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormCatalog.php';
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormSchema.php';
use Brebo\Office\Process\OfficeProcessFormCatalog as Catalog;
use Brebo\Office\Process\OfficeProcessFormSchema as Schema;
$items = Catalog::all();
if (count($items) !== 21 || count(array_unique(array_keys($items))) !== 21) {
    throw new RuntimeException('Expected 21 distinct controlled forms');
}
foreach ($items as $code => $entry) {
    if (!preg_match('/^(SJ|FR|CL|RG)-[0-9]{3}$/', $code) || count($entry) !== 2 || trim($entry[0]) === '' || trim($entry[1]) === '') {
        throw new RuntimeException('Invalid catalog entry: '.$code);
    }
    if (Catalog::requireCode($code) !== $entry) throw new RuntimeException('Lookup mismatch');
}
try { Catalog::requireCode('FR-999'); throw new RuntimeException('Unknown code accepted'); }
catch (InvalidArgumentException $expected) {}
$sql = Schema::statements();
if (count($sql) !== 3) throw new RuntimeException('Expected instance, audit and evidence tables');
foreach (['office_process_form_instances','office_process_form_events','office_process_form_evidence'] as $table) {
    if (!str_contains(implode("\n",$sql), 'CREATE TABLE IF NOT EXISTS '.$table)) throw new RuntimeException('Missing table '.$table);
}
if (!str_contains($sql[1], 'UNIQUE KEY uq_process_form_event_revision (form_id,revision)')) throw new RuntimeException('Missing revision uniqueness');
echo "21 process form catalog and schema contracts OK\n";
