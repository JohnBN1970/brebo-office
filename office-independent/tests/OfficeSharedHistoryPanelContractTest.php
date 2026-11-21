<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Shared/OfficeHistoryPanel.php';

use Brebo\Office\Shared\OfficeHistoryPanel;

$html = OfficeHistoryPanel::render([
    ['actor' => '<script>alert(1)</script>', 'action' => 'Fase teruggezet', 'occurred_at' => '2026-11-21 08:30', 'detail' => 'Gekwalificeerd → Contact gelegd'],
]);
if (!str_contains($html, '<details class="office-history-disclosure" open>') || !str_contains($html, '<summary class="office-history-toggle">Historie</summary>')) {
    throw new RuntimeException('History disclosure must be accessible and initially expanded');
}
if (str_contains($html, '<script>') || !str_contains($html, '&lt;script&gt;')) {
    throw new RuntimeException('History output must escape untrusted content');
}
foreach (['Fase teruggezet', 'Gekwalificeerd', 'Contact gelegd', 'office-history-panel'] as $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException('Missing history detail: '.$needle);
    }
}
$empty = OfficeHistoryPanel::render([]);
if (!str_contains($empty, 'Nog geen gebeurtenissen.')) {
    throw new RuntimeException('Missing empty history state');
}
echo "Office shared history panel contract OK\n";
