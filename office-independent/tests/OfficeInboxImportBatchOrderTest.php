<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeInboxImportBatch.php';

use Brebo\Office\Communication\OfficeInboxImportBatch;

// Invalid batches must fail before any bridge, repository or attachment write.
$importer = (new ReflectionClass(OfficeInboxImportBatch::class))->newInstanceWithoutConstructor();
$source = ['raw_headers'=>'From: test@example.test', 'body'=>'hello', 'mime_decoded'=>true];
foreach ([[4,4], [5,3]] as $uids) {
    $batch = ['uidvalidity'=>17, 'messages'=>array_map(
        static fn(int $uid): array => ['uid'=>$uid, 'source'=>$source], $uids
    )];
    try {
        $importer->import(1, 'INBOX', $batch);
        throw new RuntimeException('Duplicate or unordered UIDs accepted.');
    } catch (InvalidArgumentException $expected) {
        if ($expected->getMessage() !== 'IMAP batch UIDs must be strictly increasing.') throw $expected;
    }
}
echo "OfficeInboxImportBatchOrderTest PASS\n";
