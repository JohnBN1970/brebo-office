<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * Canonical form-to-Office context mapping. This is routing metadata only;
 * resource existence and access are checked by the host Office service.
 */
final class OfficeProcessFormContext
{
    private const ALLOWED = [
        'office' => ['organization'],
        'crm_leads' => ['lead'],
        'buildings_inspection' => ['building','project'],
        'calculation' => ['calculation'],
        'deals_offers' => ['deal','offer'],
        'projects_startdossier' => ['project'],
        'planning' => ['project'],
        'procurement' => ['project'],
        'projects_execution' => ['project'],
        'projects_onsite' => ['project'],
        'quality' => ['project'],
        'projects_calculation' => ['project'],
        'projects_handover' => ['project'],
        'service' => ['project','building'],
        'projects' => ['project'],
        'calculation_finance' => ['project'],
        'finance' => ['project'],
        'contracts' => ['project','relation'],
    ];

    public static function accepts(string $code, string $type): bool
    {
        require_once __DIR__.'/OfficeProcessFormCatalog.php';
        $entry=OfficeProcessFormCatalog::get($code);
        return $entry !== null && in_array($type,self::ALLOWED[$entry[1]]??[],true);
    }

    public static function typesFor(string $code): array
    {
        require_once __DIR__.'/OfficeProcessFormCatalog.php';
        $entry=OfficeProcessFormCatalog::requireCode($code);
        return self::ALLOWED[$entry[1]]??[];
    }
}
