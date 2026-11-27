<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use InvalidArgumentException;

/**
 * Category-driven workflow selection for CRM Deals.
 * Pure policy: no schema changes, no side effects, no bypass of existing ACL.
 * The caller supplies the service category explicitly; work_categories are
 * activities and must not be mistaken for a service category.
 */
final class OfficeDealServiceWorkflow {
    public const WORKFLOWS = [
        'uitvoering' => ['preparation' => 'calculation', 'offer' => 'offertestudio', 'project_tools' => ['werkvoorbereiding','uitvoering','werkbegroting']],
        'mjop' => ['preparation' => 'service_quote', 'offer' => 'offertestudio', 'project_tools' => ['mjop','rapportage']],
        'inspectie' => ['preparation' => 'service_quote', 'offer' => 'offertestudio', 'project_tools' => ['inspectie','rapportage']],
        'advies' => ['preparation' => 'service_quote', 'offer' => 'offertestudio', 'project_tools' => ['advies','rapportage']],
        'projectmanagement' => ['preparation' => 'service_quote', 'offer' => 'offertestudio', 'project_tools' => ['wbs','rbs','projectbeheersing']],
    ];

    public static function resolve(array $serviceCategories): array {
        if ($serviceCategories === [] || count($serviceCategories) !== count(array_unique($serviceCategories))) {
            throw new InvalidArgumentException('One or more unique service categories required');
        }
        $preparations=[]; $tools=[];
        foreach ($serviceCategories as $category) {
            if (!is_string($category) || !isset(self::WORKFLOWS[$category])) {
                throw new InvalidArgumentException('Unknown service category');
            }
            $workflow=self::WORKFLOWS[$category];
            $preparations[]=$workflow['preparation'];
            array_push($tools,...$workflow['project_tools']);
        }
        return [
            'service_categories'=>array_values($serviceCategories),
            'preparations'=>array_values(array_unique($preparations)),
            'offer'=>'offertestudio',
            'project_tools'=>array_values(array_unique($tools)),
            'calculation_required'=>in_array('calculation',$preparations,true),
        ];
    }
}
