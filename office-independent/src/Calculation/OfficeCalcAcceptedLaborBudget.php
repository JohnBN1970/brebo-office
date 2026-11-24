<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Immutable labor-hours handoff contract for one accepted calculation revision.
 * Not a replacement for the locked financial baseline or its decimal line contract.
 * Never derive hours from cost totals or from crew-days.
 */
final class OfficeCalcAcceptedLaborBudget
{
    public static function prepare(array $acceptance, array $sourceLines): array
    {
        if (($acceptance['status']??null)!=='accepted'
            || !is_int($acceptance['calculation_id']??null)
            || $acceptance['calculation_id']<1
            || !is_string($acceptance['accepted_revision']??null)
            || trim($acceptance['accepted_revision'])===''
            || !is_string($acceptance['crm_opportunity_id']??null)
            || trim($acceptance['crm_opportunity_id'])==='') {
            throw new DomainException('Geaccepteerde calculatie met revisie vereist.');
        }
        if ($sourceLines===[])throw new DomainException('Lege calculatie.');
        require_once __DIR__.'/OfficeCalcProjectLaborTotal.php';
        $seen=[];
        foreach ($sourceLines as $line) {
            if (!is_array($line)||!is_string($line['line_id']??null)
                || $line['line_id']===''||isset($seen[$line['line_id']])) {
                throw new DomainException('Ongeldige of dubbele bronregel.');
            }
            $seen[$line['line_id']]=true;
        }
        $total=OfficeCalcProjectLaborTotal::summarize($sourceLines);
        if (!$total['complete'])throw new DomainException('Arbeidsuren niet volledig berekend.');
        return [
            'source'=>'accepted_crm_calculation',
            'crm_opportunity_id'=>$acceptance['crm_opportunity_id'],
            'calculation_id'=>$acceptance['calculation_id'],
            'accepted_revision'=>$acceptance['accepted_revision'],
            'labor_hours'=>$total['hours'],
            'labor_lines'=>$total['labor_lines'],
            'locked'=>true,
        ];
    }
}
