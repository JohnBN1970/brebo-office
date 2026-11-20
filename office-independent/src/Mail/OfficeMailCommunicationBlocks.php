<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use DomainException;

/**
 * Native Office contract: one mail source, many verified dossier projections.
 * Does not create business transitions or grant access to source messages.
 */
final class OfficeMailCommunicationBlocks
{
    private const TYPES = [
        'crm' => ['relation', 'deal'],
        'projecten' => ['project'],
        'calculatie' => ['calculation'],
        'finance' => ['invoice'],
        'documenten' => ['document'],
        'acties' => ['action'],
    ];

    public static function prepare(string $messageId, array $links, int $reviewerId): array
    {
        $messageId = trim($messageId);
        if ($messageId === '' || strlen($messageId) > 255 || $reviewerId < 1) {
            throw new DomainException('verified_mail_identity_and_reviewer_required');
        }
        $unique = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                throw new DomainException('invalid_mail_context_link');
            }
            $module = $link['module'] ?? null;
            $type = $link['object_type'] ?? null;
            $id = $link['object_id'] ?? null;
            if (!is_string($module) || !is_string($type) || !is_int($id)
                || $id < 1 || !in_array($type, self::TYPES[$module] ?? [], true)
                || ($link['verified'] ?? null) !== true) {
                throw new DomainException('unverified_or_unknown_mail_context_link');
            }
            $key = $module . ':' . $type . ':' . $id;
            $unique[$key] = [
                'module' => $module,
                'object_type' => $type,
                'object_id' => $id,
                'state' => 'confirmed',
                'confirmed_by' => $reviewerId,
            ];
        }
        return [
            'source' => 'email',
            'source_message_id' => $messageId,
            'links' => array_values($unique),
            'requires_destination_acl' => true,
            'requires_source_mail_acl' => true,
            'creates_business_transition' => false,
        ];
    }
}
