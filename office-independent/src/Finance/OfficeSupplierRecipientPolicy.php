<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Pure, side-effect-free payment recipient verification policy.
 * Caller must load Office-owned master data and independent verification evidence.
 * Never treats a Moneybird contact or imported IBAN as verification evidence.
 */
final class OfficeSupplierRecipientPolicy {
    public static function normalizeIban(string $iban): string {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $iban));
    }

    public static function validIban(string $iban): bool {
        // Only spacing is tolerated. Never silently strip punctuation or other characters.
        if (!preg_match('/^[A-Za-z0-9\\s]+$/D', $iban)) return false;
        $iban = self::normalizeIban($iban);
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $iban)) return false;
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $char) {
            $digits = ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
            foreach (str_split($digits) as $digit) $remainder = ($remainder * 10 + (int) $digit) % 97;
        }
        return $remainder === 1;
    }

    /** @param array<string,mixed> $account Office-owned account and verification record */
    public static function verifiedSnapshot(array $account, int $invoiceId, int $now): array {
        $rawIban = (string) ($account['iban'] ?? '');
        $iban = self::normalizeIban($rawIban);
        $holder = trim((string) ($account['account_holder'] ?? ''));
        $relationId = (int) ($account['relation_id'] ?? 0);
        $verifiedAt = (int) ($account['verified_at'] ?? 0);
        $verifiedBy = (int) ($account['verified_by'] ?? 0);
        $changedAt = (int) ($account['changed_at'] ?? 0);
        $evidenceId = trim((string) ($account['verification_evidence_id'] ?? ''));
        if ($invoiceId <= 0 || $relationId <= 0 || !self::validIban($rawIban) || $holder === ''
            || ($account['status'] ?? '') !== 'active'
            || ($account['verification_status'] ?? '') !== 'verified'
            || $verifiedAt <= 0 || $verifiedAt > $now || $verifiedBy <= 0
            || $evidenceId === '' || $changedAt > $verifiedAt) {
            throw new \RuntimeException('Payment blocked: Office supplier recipient is not independently verified.');
        }
        $canonical = [
            'invoice_id' => $invoiceId,
            'relation_id' => $relationId,
            'iban' => $iban,
            'account_name' => $holder,
            'bic' => strtoupper(trim((string) ($account['bic'] ?? ''))),
            'verification_evidence_id' => $evidenceId,
            'verified_at' => $verifiedAt,
            'verified_by' => $verifiedBy,
        ];
        return $canonical + ['recipient_hash' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR))];
    }

    /** Mandatory authorization and recipient checks remain even when four-eyes is disabled. */
    public static function canRelease(bool $authorized, bool $controllerGreen, bool $recipientUnchanged,
        int $preparerId, int $releaserId, bool $fourEyesRequired): bool {
        return $authorized && $controllerGreen && $recipientUnchanged && $preparerId > 0 && $releaserId > 0
            && (!$fourEyesRequired || $preparerId !== $releaserId);
    }
}
