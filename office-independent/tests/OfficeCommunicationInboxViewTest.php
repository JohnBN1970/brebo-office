<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Communication/OfficeCommunicationInboxView.php';

use Brebo\Office\Communication\OfficeCommunicationInboxView;

$empty = OfficeCommunicationInboxView::render([]);
if (!str_contains($empty, 'geen berichten')) {
    throw new RuntimeException('Empty state missing.');
}
$view = OfficeCommunicationInboxView::render([[
    'received_at' => '2026-10-31',
    'sender_address' => '<script>alert(1)</script>',
    'subject' => '<img src=x onerror=alert(1)>',
    'processing_status' => 'stored',
]]);
if (str_contains($view, '<script>') || str_contains($view, '<img')) {
    throw new RuntimeException('Unsafe inbox output.');
}
if (!str_contains($view, '&lt;script&gt;') || !str_contains($view, '&lt;img')) {
    throw new RuntimeException('Inbox escaping missing.');
}
echo "Office communication inbox view OK\n";
