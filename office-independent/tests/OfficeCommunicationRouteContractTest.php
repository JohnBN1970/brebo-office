<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Communication/OfficeCommunicationAccess.php';
require_once __DIR__ . '/../src/Communication/OfficeCommunicationInboxView.php';
use Brebo\Office\Communication\OfficeCommunicationInboxView;
$view = OfficeCommunicationInboxView::render([[
  'received_at' => '2026-10-31',
  'sender_address' => '<script>alert(1)</script>',
  'subject' => '<img src=x>',
  'processing_status' => 'pending',
]]);
if (str_contains($view, '<script>') || str_contains($view, '<img')) {
  throw new RuntimeException('Unescaped mail content');
}
$routes = require __DIR__ . '/../modules/office-communication/routes.php';
if (($routes['/office-test/communicatie'] ?? null) !== 'office-communication.php') {
  throw new RuntimeException('Communication route missing');
}
$source = file_get_contents(__DIR__ . '/../public/office-communication.php');
foreach (['OFFICE_COMMUNICATION_ENABLED','OFFICE_COMMUNICATION_SCHEMA_VERIFIED','OfficeCommunicationAccess','u213420663_office_test'] as $needle) {
  if (!str_contains($source, $needle)) throw new RuntimeException('Route guard missing: '.$needle);
}
echo "Office communication route contract OK\n";
