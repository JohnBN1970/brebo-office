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

$encoded = "From: outside@example.com\r\n"
  . "To: john@mail-test.example.nl\r\n"
  . "Subject: Encoded\r\n"
  . "Content-Type: text/plain; charset=UTF-8\r\n"
  . "Content-Transfer-Encoding: base64\r\n\r\n"
  . base64_encode('Leesbare inhoud');
$decoded = (new RawMailNormalizer())->normalize($encoded, 'john@mail-test.example.nl');
if (($decoded['text'] ?? '') !== 'Leesbare inhoud') {
  throw new RuntimeException('Base64 text decoding failed.');
}

$empty = "From: outside@example.com\r\nTo: john@mail-test.example.nl\r\n\r\n";
$validEmpty = (new RawMailNormalizer())->normalize($empty, 'john@mail-test.example.nl');
if (($validEmpty['subject'] ?? NULL) !== '' || ($validEmpty['text'] ?? NULL) !== '') {
  throw new RuntimeException('Valid empty subject/body mail must be accepted.');
}

$multipart = "From: outside@example.com\r\n"
  . "To: john@mail-test.example.nl\r\n"
  . "Subject: Multipart\r\n"
  . "Content-Type: multipart/alternative; boundary=abc123\r\n\r\n"
  . "--abc123\r\n"
  . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
  . "Platte tekst\r\n"
  . "--abc123\r\n"
  . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
  . "<p>HTML tekst</p>\r\n"
  . "--abc123--\r\n";
$multipartMessage = (new RawMailNormalizer())->normalize($multipart, 'john@mail-test.example.nl');
if (($multipartMessage['text'] ?? '') !== 'Platte tekst') {
  throw new RuntimeException('Multipart plain-text preference failed.');
}

$latin1Body = quoted_printable_encode(mb_convert_encoding('café', 'ISO-8859-1', 'UTF-8'));
$latin1 = "From: outside@example.com\r\n"
  . "To: john@mail-test.example.nl\r\n"
  . "Subject: Charset\r\n"
  . "Content-Type: text/plain; charset=ISO-8859-1\r\n"
  . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
  . $latin1Body;
$latin1Message = (new RawMailNormalizer())->normalize($latin1, 'john@mail-test.example.nl');
if (($latin1Message['text'] ?? '') !== 'café') {
  throw new RuntimeException('Charset conversion to UTF-8 failed.');
}

echo "BREBO_MAIL_RAW_NORMALIZER=PASS\n";
