<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/OfficeIntakeSourceAuthenticator.php';

use Brebo\Office\Intake\OfficeIntakeSourceAuthenticator;

$now = 1793836800;
$timestamp = (string) $now;
$body = '{"request_id":"WEB-001","disciplines":["bouw"]}';
$key = str_repeat('w', 48);
$auth = new OfficeIntakeSourceAuthenticator(['website' => $key]);
$signature = 'sha256=' . hash_hmac('sha256', "website\n" . $timestamp . "\n" . $body, $key);
$auth->verify('website', $body, $timestamp, $signature, $now);
foreach ([
    ['email', $body, $timestamp, $signature, $now],
    ['website', $body . ' ', $timestamp, $signature, $now],
    ['website', $body, (string) ($now - 301), $signature, $now],
    ['website', $body, 'invalid', $signature, $now],
    ['website', $body, $timestamp, 'sha256=' . str_repeat('0', 64), $now],
    ['website', $body, $timestamp, 'invalid', $now],
] as $bad) {
    try {
        $auth->verify(...$bad);
        throw new RuntimeException('invalid_signature_accepted');
    } catch (DomainException $expected) {
    }
}
try {
    (new OfficeIntakeSourceAuthenticator(['website' => 'short']))->verify(
        'website', $body, $timestamp, $signature, $now
    );
    throw new RuntimeException('weak_source_key_accepted');
} catch (DomainException $expected) {
}
echo "OfficeIntakeSourceAuthenticatorTest OK\n";
