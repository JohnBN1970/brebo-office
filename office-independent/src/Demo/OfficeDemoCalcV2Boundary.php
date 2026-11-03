<?php

declare(strict_types=1);

namespace Brebo\Office\Demo;

use InvalidArgumentException;

/**
 * Explicit adapter boundary between Office demo scope and Workspace v2.
 * A scope is never itself a calculation; only the calculation authority
 * can allocate an ID and grant access. This class does not send requests.
 */
final class OfficeDemoCalcV2Boundary
{
    public static function prepare(array $handoff): array
    {
        if (($handoff['contract'] ?? null) !== 'brebo-office-demo-calc-handoff-v1'
            || ($handoff['project_code'] ?? null) !== 'TEST-2026-001'
            || ($handoff['quantity_basis'] ?? null) !== 'per_dwelling'
            || ($handoff['quantity_source'] ?? null) !== 'synthetic_demo_not_measured'
            || ($handoff['price_status'] ?? null) !== 'unpriced'
            || !in_array($handoff['status'] ?? null, ['AWAITING_CANONICAL_CALC_LINK','LINKED_REFERENCE_UNVERIFIED'], true)) {
            throw new InvalidArgumentException('Invalid Office handoff');
        }
        foreach (['project_id','scope_id','building_id','housing_type_id'] as $key) {
            if (!isset($handoff[$key]) || !is_int($handoff[$key]) || $handoff[$key] < 1) {
                throw new InvalidArgumentException('Invalid Office identity');
            }
        }
        $calcId=$handoff['calculation_id'] ?? null;
        if ($calcId !== null && (!is_int($calcId) || $calcId < 1)) {
            throw new InvalidArgumentException('Invalid calculation reference');
        }
        if (($calcId===null) !== ($handoff['status']==='AWAITING_CANONICAL_CALC_LINK')) {
            throw new InvalidArgumentException('Inconsistent calculation reference');
        }
        $lines=$handoff['lines'] ?? null;
        if (!is_array($lines) || count($lines)!==3) throw new InvalidArgumentException('Invalid lines');
        $codes=[];
        $normalized=[];
        foreach($lines as $line) {
            if (!is_array($line)) throw new InvalidArgumentException('Invalid line');
            $code=$line['source_finding_code']??null;
            $quantity=$line['quantity_per_dwelling']??null;
            if (!is_string($code) || !in_array($code,['DEMO-B01','DEMO-B02','DEMO-B03'],true)
                || isset($codes[$code]) || !is_string($line['description']??null)
                || trim($line['description'])==='' || !is_string($line['unit']??null)
                || trim($line['unit'])==='' || !is_numeric($quantity)
                || !is_finite((float)$quantity) || (float)$quantity<=0) {
                throw new InvalidArgumentException('Invalid demo line');
            }
            $codes[$code]=true;
            $normalized[]=[
                'source_finding_code'=>$code,
                'description'=>$line['description'],
                'unit'=>$line['unit'],
                'quantity_per_dwelling'=>(float)$quantity,
            ];
        }
        usort($normalized,static fn(array $a,array $b):int=>strcmp($a['source_finding_code'],$b['source_finding_code']));
        return [
            'contract'=>'brebo-office-demo-calc-v2-preparation-v1',
            'project_id'=>$handoff['project_id'],
            'scope_id'=>$handoff['scope_id'],
            'building_id'=>$handoff['building_id'],
            'housing_type_id'=>$handoff['housing_type_id'],
            'calculation_id'=>$calcId,
            'status'=>$calcId===null?'BLOCKED_CALC_AUTHORITY_REQUIRED':'BLOCKED_CALC_ACCESS_VERIFICATION_REQUIRED',
            'external_write_allowed'=>false,
            'quantity_basis'=>'per_dwelling',
            'source_kind'=>'synthetic_demo_not_measured',
            'pricing'=>'not_supplied',
            'lines'=>$normalized,
        ];
    }
}
