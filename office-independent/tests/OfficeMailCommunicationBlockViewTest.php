<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Mail/OfficeMailCommunicationBlockView.php';

use Brebo\Office\Mail\OfficeMailCommunicationBlockView;

$rows = [['message_id' => 'mail-1'], ['message_id' => 'mail-2']];
$rendered = OfficeMailCommunicationBlockView::render($rows, static function (string $id): array {
    if ($id === 'mail-2') {
        return ['authorized' => false, 'subject' => 'SECRET'];
    }
    return ['authorized' => true, 'subject' => '<script>alert(1)</script>', 'sender' => 'Test & Co', 'date' => '2026-11-20'];
});
if (str_contains($rendered, '<script>')
    || !str_contains($rendered, '&lt;script&gt;')
    || !str_contains($rendered, 'Test &amp; Co')
    || str_contains($rendered, 'SECRET')) {
    throw new RuntimeException('Communication view ACL or escaping failed.');
}
$empty = OfficeMailCommunicationBlockView::render([], static fn (): array => []);
if (!str_contains($empty, 'Geen gekoppelde e-mails')) {
    throw new RuntimeException('Empty communication view failed.');
}
echo "OFFICE_MAIL_COMMUNICATION_VIEW_OK\n";
