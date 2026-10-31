<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeImapReadOnlySource.php';
use Brebo\Office\Communication\OfficeImapReadOnlySource;
foreach ([['',993,'user','secret'], ['imap.example.test',0,'user','secret'], ['imap.example.test',993,'user','']] as $args) {
    try {
        new OfficeImapReadOnlySource(...$args);
        throw new RuntimeException('Invalid source configuration accepted.');
    } catch (InvalidArgumentException $expected) {}
}
$source = new OfficeImapReadOnlySource('imap.example.test', 993, 'user', 'secret');
foreach ([[-1,25],[0,0],[0,101]] as [$uid,$limit]) {
    try {
        $source->fetchAfter($uid,$limit);
        throw new RuntimeException('Invalid cursor accepted.');
    } catch (InvalidArgumentException $expected) {}
}
echo "OfficeImapReadOnlySourceTest PASS\n";
