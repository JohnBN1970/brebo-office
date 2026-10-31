<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeMimeMessageDecoder.php';
use Brebo\Office\Communication\OfficeMimeMessageDecoder;

$decoder = new OfficeMimeMessageDecoder();
$cases = [
    ['Hallo BREBO', 'text/plain', '7bit', 'Hallo BREBO'],
    ['SGFsbG8gQlJFQk8=', 'text/plain', 'base64', 'Hallo BREBO'],
    ['Factuur=20ontvangen', 'text/plain', 'quoted-printable', 'Factuur ontvangen'],
    ['<p>Factuur ontvangen</p>', 'text/html', '8bit', 'Factuur ontvangen'],
];
foreach ($cases as [$raw, $mime, $encoding, $expected]) {
    $result = $decoder->decode($raw, $mime, $encoding);
    if ($result['body'] !== $expected || $result['attachments'] !== []) {
        throw new RuntimeException('MIME decoding regression.');
    }
}
foreach ([
    ['---', 'multipart/mixed; boundary=abc', '7bit'],
    ['broken!', 'text/plain', 'base64'],
    ['abc', 'application/pdf', '7bit'],
    ['abc', 'text/plain', 'unknown'],
] as [$raw, $mime, $encoding]) {
    $rejected = false;
    try { $decoder->decode($raw, $mime, $encoding); }
    catch (RuntimeException $e) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('Unsafe MIME accepted.');
}
echo "OfficeMimeMessageDecoderTest PASS\n";
