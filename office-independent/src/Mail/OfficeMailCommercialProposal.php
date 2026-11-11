<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use InvalidArgumentException;

/**
 * Trusted Office boundary for a proposed classification, not an AI model.
 * Mail content is untrusted. This class never writes CRM/project records.
 */
final class OfficeMailCommercialProposal
{
    private const INTENTS = [
        'new_enquiry' => 'crm_intake_review',
        'quote_request' => 'crm_opportunity_review',
        'quote_acceptance' => 'acceptance_review',
        'direct_order' => 'direct_order_review',
        'project_correspondence' => 'project_link_review',
        'invoice' => 'finance_review',
        'inspection_request' => 'inspection_review',
        'other' => 'manual_review',
    ];

    public static function prepare(array $classification): array
    {
        $messageId = $classification['message_id'] ?? null;
        $intent = $classification['intent'] ?? null;
        $confidence = $classification['confidence'] ?? null;
        if (!is_string($messageId) || trim($messageId) === '' || strlen($messageId) > 255) {
            throw new InvalidArgumentException('invalid_message_id');
        }
        if (!is_string($intent) || !array_key_exists($intent, self::INTENTS)) {
            throw new InvalidArgumentException('invalid_mail_intent');
        }
        if (!is_numeric($confidence) || !is_finite((float)$confidence)
            || (float)$confidence < 0 || (float)$confidence > 1) {
            throw new InvalidArgumentException('invalid_confidence');
        }
        $candidates = $classification['candidates'] ?? [];
        if (!is_array($candidates)) {
            throw new InvalidArgumentException('invalid_candidates');
        }
        $links = [];
        foreach (['relation_id', 'opportunity_id', 'project_id', 'building_id'] as $key) {
            $value = $candidates[$key] ?? null;
            if ($value !== null && (!is_int($value) || $value < 1)) {
                throw new InvalidArgumentException('unverified_candidate_identity');
            }
            $links[$key] = $value;
        }
        return [
            'message_id' => trim($messageId),
            'intent' => $intent,
            'confidence' => (float)$confidence,
            'suggested_action' => self::INTENTS[$intent],
            'candidate_links' => $links,
            'status' => 'PENDING_HUMAN_REVIEW',
            'automated_acceptance_allowed' => false,
            'automated_project_creation_allowed' => false,
            'automated_invoice_approval_allowed' => false,
            'canonical_office_only' => true,
        ];
    }
}
