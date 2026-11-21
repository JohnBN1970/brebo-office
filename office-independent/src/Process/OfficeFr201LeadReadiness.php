<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * Read-only FR-201 readiness assessment for the existing lightweight CRM lead.
 * Leads are intentionally not full intake forms: qualification may collect the
 * missing facts before handoff to opname/calculatie. No schema or write side effects.
 */
final class OfficeFr201LeadReadiness
{
    /** @return array{document_code:string,document_version:string,complete:bool,missing:list<string>,known:array<string,string>} */
    public static function assess(array $lead, array $supplement = []): array
    {
        require_once __DIR__.'/OfficeFr201Intake.php';
        $known = [
            'channel' => self::string($lead['source_label'] ?? ''),
            'source_reference' => self::string($lead['source_detail'] ?? ''),
            'contact_name' => self::string($lead['contact_name'] ?? ''),
            'contact_email' => self::string($lead['contact_email'] ?? ''),
            'customer_question' => self::string($lead['description'] ?? ''),
        ];
        // Explicit supplementary values are allowed, but never infer REL-ID,
        // contractual identity, project location, scope, or customer authority.
        foreach (OfficeFr201Intake::fields() as $key => $_) {
            if (in_array($key, ['channel','source_reference','contact_name','contact_email','customer_question','relation_id','owner_user_id'], true)) continue;
            if (array_key_exists($key, $supplement) && is_scalar($supplement[$key])) {
                $known[$key] = trim((string)$supplement[$key]);
            }
        }
        $missing = [];
        foreach (OfficeFr201Intake::fields() as $key => $definition) {
            if ($definition['required'] && trim($known[$key] ?? '') === '') $missing[] = $key;
        }
        return [
            'document_code' => OfficeFr201Intake::CODE,
            'document_version' => OfficeFr201Intake::VERSION,
            'complete' => $missing === [],
            'missing' => $missing,
            'known' => $known,
        ];
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
