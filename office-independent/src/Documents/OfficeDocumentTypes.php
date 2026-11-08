<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

/**
 * Central document creation contract. This registry does not grant access or
 * create documents: controllers must authorize the selected type and context.
 */
final class OfficeDocumentTypes
{
    public const CONTEXTS = ['documenten', 'crm', 'projecten', 'gebouwen', 'bedrijfsvoering', 'personeel'];

    private const TYPES = [
        'bouwkundige-inspectie' => ['Bouwkundig inspectierapport', ['documenten', 'projecten', 'gebouwen']],
        'bouwkundige-expertise' => ['Bouwkundig expertiserapport', ['documenten', 'projecten', 'gebouwen']],
        'mjop' => ['MJOP-rapport', ['documenten', 'projecten', 'gebouwen']],
        'offerte' => ['Offerte', ['documenten', 'crm', 'projecten']],
        'werkopdracht' => ['Werkopdracht', ['documenten', 'projecten']],
        'opleverrapport' => ['Opleverrapport', ['documenten', 'projecten', 'gebouwen']],
        'correspondentie' => ['Brief / correspondentie', ['documenten', 'crm', 'projecten', 'bedrijfsvoering', 'personeel']],
        'overeenkomst' => ['Overeenkomst', ['documenten', 'crm', 'bedrijfsvoering']],
        'financieel-document' => ['Financieel document', ['documenten', 'bedrijfsvoering']],
        'personeelsdocument' => ['Personeelsdocument', ['documenten', 'personeel']],
        'certificaat' => ['Certificaat', ['documenten', 'personeel']],
        'overig' => ['Overig document', self::CONTEXTS],
    ];

    public static function forContext(string $context): array
    {
        if (!in_array($context, self::CONTEXTS, true)) {
            return [];
        }
        $result = [];
        foreach (self::TYPES as $key => [$label, $contexts]) {
            if (in_array($context, $contexts, true)) {
                $result[$key] = $label;
            }
        }
        return $result;
    }

    public static function isAllowedInContext(string $type, string $context): bool
    {
        return array_key_exists($type, self::forContext($context));
    }
}
