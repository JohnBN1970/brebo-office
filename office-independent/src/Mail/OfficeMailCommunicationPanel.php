<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

require_once __DIR__ . '/OfficeMailCommunicationBlockRepository.php';
require_once __DIR__ . '/OfficeMailCommunicationBlockView.php';

/**
 * Integration boundary for native Office dossier pages.
 *
 * A caller must provide the current operator's source and target ACL checks
 * and a mail-summary provider that independently enforces source mail ACL.
 */
final class OfficeMailCommunicationPanel
{
    public function __construct(private OfficeMailCommunicationBlockRepository $repository) {}

    public function renderFor(
        string $module,
        string $objectType,
        int $objectId,
        callable $canReadMail,
        callable $canReadTarget,
        callable $authorizedMailSummary
    ): string {
        $rows = $this->repository->visibleFor(
            $module, $objectType, $objectId, $canReadMail, $canReadTarget
        );
        return OfficeMailCommunicationBlockView::render(
            $rows,
            static function (string $messageId) use ($canReadMail, $authorizedMailSummary): array {
                // Fail closed even if a caller's summary provider forgets ACL.
                if ($canReadMail($messageId) !== true) {
                    return ['authorized' => false];
                }
                $summary = $authorizedMailSummary($messageId);
                return is_array($summary) && ($summary['authorized'] ?? false) === true
                    ? $summary
                    : ['authorized' => false];
            }
        );
    }
}
