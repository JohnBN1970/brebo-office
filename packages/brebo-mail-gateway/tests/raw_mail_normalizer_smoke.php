<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Service\RawMailNormalizer;

$raw = "From: Buiten <outside@example.com>\r\n"
  . "To: John <john@mail-test.example.nl>\r\n"
  . "Subject: Inbound via Postfix\r\n"
  . "Message-ID: <abc-123@example.com>\r\n"
  . "Date: Tue, 6 Oct 2026 01:00:00 +0000\r\n"
  . "\r\n"
  . "Dit is een echt inbound testbericht.\r\n";

$message = (new RawMailNormalizer())->normalize($raw, 'john@mail-test.example.nl');
if (($message['source_id'] ?? '') !== 'smtp:abc-123@example.com') {
  throw new RuntimeException('Message-ID source normalization failed.');
}
if (($message['from'] ?? '') !== 'outside@example.com') {
  throw new RuntimeException('From normalization failed.');
}
if (($message['to'] ?? '') !== 'john@mail-test.example.nl') {
  throw new RuntimeException('Envelope recipient must be canonical recipient.');
}
if (($message['subject'] ?? '') !== 'Inbound via Postfix') {
  throw new RuntimeException('Subject normalization failed.');
}
if (!str_contains((string) ($message['text'] ?? ''), 'echt inbound testbericht')) {
  throw new RuntimeException('Body normalization failed.');
}

echo "BREBO_MAIL_RAW_NORMALIZER=PASS\n";
