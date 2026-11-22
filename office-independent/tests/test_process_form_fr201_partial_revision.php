<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/src/Process/OfficeFr201Repository.php');
foreach ([
    'FOR UPDATE',
    "if (\$current === false && \$expectedRevision !== 0)",
    "if (\$current !== false && (int)\$current['revision'] !== \$expectedRevision)",
    'array_replace($existing,$data)',
    'json_encode($data, JSON_THROW_ON_ERROR',
    'rollBack()',
] as $contract) {
    if (!str_contains($source,$contract)) throw new RuntimeException('FR-201 partial revision contract missing: '.$contract);
}
$existing=['location'=>'Adres A','planning'=>'Volgende maand'];
$update=['planning'=>'Volgende week'];
if (array_replace($existing,$update)!==['location'=>'Adres A','planning'=>'Volgende week']) {
    throw new RuntimeException('FR-201 partial merge lost a field');
}
echo "FR-201 partial revision guards OK\n";
