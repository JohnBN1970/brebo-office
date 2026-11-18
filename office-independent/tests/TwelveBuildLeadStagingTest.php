<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildLeadStaging.php';

use Brebo\Office\Integrations\TwelveBuild\TwelveBuildLeadStaging;

$records = [['id' => 'A_123', 'title' => 'Example'], ['id' => 'A_123', 'title' => 'Duplicate'], ['id' => 'B_456', 'title' => 'Second']];
$mapped = TwelveBuildLeadStaging::candidates($records, static fn (array $r): array => ['external_id' => $r['id'], 'title' => $r['title']]);
if (count($mapped) !== 2 || $mapped[0]['source_key'] !== '12build:A_123' || $mapped[1]['status'] !== 'review_required') {
    throw new RuntimeException('12Build staging deduplication failed.');
}
try {
    TwelveBuildLeadStaging::candidates([['id' => '../bad']], static fn (array $r): array => ['external_id' => $r['id']]);
    throw new RuntimeException('Unsafe external ID accepted.');
} catch (UnexpectedValueException $expected) {
}
echo "12Build lead staging fixture tests passed\n";
