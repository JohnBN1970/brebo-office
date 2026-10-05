<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Infrastructure\InMemoryMailboxStore;
use Brebo\MailGateway\Infrastructure\InMemorySmtpTransport;
use Brebo\MailGateway\Service\MailTransportService;

$smtp = new InMemorySmtpTransport();
$store = new InMemoryMailboxStore();
$transport = new MailTransportService($smtp, $store);

$reference = $transport->send([
  'from' => 'john@mail-test.example.nl',
  'to' => 'outside@example.com',
  'subject' => 'BREBO Mail test',
  'text' => 'Testbericht',
]);
if (!str_starts_with($reference, 'smtp-test:')) {
  throw new RuntimeException('SMTP testtransport failed.');
}
$sent = $store->recent('john@mail-test.example.nl', 'Sent');
if (count($sent) !== 1 || ($sent[0]['subject'] ?? '') !== 'BREBO Mail test') {
  throw new RuntimeException('Sent mailbox projection failed.');
}

$received = $transport->receive('john@mail-test.example.nl', [
  'from' => 'outside@example.com',
  'to' => 'john@mail-test.example.nl',
  'subject' => 'Antwoord',
  'text' => 'Testantwoord',
]);
if (!str_starts_with($received, 'store-test:')) {
  throw new RuntimeException('Inbound mailbox store failed.');
}
$inbox = $store->recent('john@mail-test.example.nl');
if (count($inbox) !== 1 || ($inbox[0]['subject'] ?? '') !== 'Antwoord') {
  throw new RuntimeException('Inbox projection failed.');
}

$health = $transport->health();
if (empty($health['smtp']['available']) || empty($health['mailbox_store']['available'])) {
  throw new RuntimeException('Mail transport health failed.');
}

echo "BREBO_MAIL_TRANSPORT_SMOKE=PASS\n";
