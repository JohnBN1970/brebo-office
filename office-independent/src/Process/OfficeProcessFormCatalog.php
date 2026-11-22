<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * Controlled document registry from the BREBO process handbook v1.0.
 * A catalog entry is not a claim of implemented/approved form fields.
 */
final class OfficeProcessFormCatalog
{
    public const DOCUMENTS = [
        'SJ-001' => ['Dossieridentiteit en bronverwijzing', 'office'],
        'FR-201' => ['Klantvraag en intake', 'crm_leads'],
        'CL-204' => ['Projectopname', 'buildings_inspection'],
        'FR-210' => ['Calculatie-uitgangspunten', 'calculation'],
        'CL-220' => ['Offerte- en opdrachttoets', 'deals_offers'],
        'FR-300' => ['Projectoverdracht', 'projects_startdossier'],
        'CL-310' => ['Planning, capaciteit en logistiek', 'planning'],
        'FR-320' => ['Inkoop- en onderaannemersvrijgave', 'procurement'],
        'CL-330' => ['Uitvoeringsgereedheid', 'projects_startdossier'],
        'FR-400' => ['Startvrijgave', 'projects_execution'],
        'RG-401' => ['Digitaal bouwdagboek', 'projects_onsite'],
        'CL-410' => ['Keurings- en kwaliteitsplan', 'quality'],
        'FR-430' => ['Wijzigings- en meerwerkformulier', 'projects_calculation'],
        'CL-500' => ['Vooroplevering', 'projects_handover'],
        'RG-501' => ['Restpuntenregister', 'projects_handover'],
        'FR-510' => ['Oplevering en overdracht', 'projects_handover'],
        'RG-520' => ['Service-, klacht- en garantieregister', 'service'],
        'CL-600' => ['Afsluitcontrole', 'projects'],
        'FR-601' => ['Nacalculatie en evaluatie', 'calculation_finance'],
        'RG-610' => ['Financiële projectbewaking', 'finance'],
        'RG-620' => ['Contract-, claim- en juridisch beheer', 'contracts'],
    ];

    public static function get(string $code): ?array
    {
        return self::DOCUMENTS[$code] ?? null;
    }

    public static function requireCode(string $code): array
    {
        return self::get($code) ?? throw new \InvalidArgumentException('Unknown controlled document code');
    }

    public static function all(): array
    {
        return self::DOCUMENTS;
    }
}
