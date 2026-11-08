<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

/** Canonical read contract for Finance/project consumers. Never writes a second ledger. */
final class OfficeProcurementOrderSource
{
    public function __construct(private readonly OfficeGovernanceService $service) {}
    /** Values are EUR cents excluding VAT; a concept or approval is not yet an awarded obligation. */
    public function awardedOrders(string $actor,int $project,int $building): array
    {
        $orders=[];
        foreach ($this->service->read($actor,'procurement',$project,$building) as $record) {
            if (!in_array($record['status'],['ordered','received'],true)) continue;
            $orders[]=['source'=>'office_procurement_orders','order_id'=>(int)$record['id'],
                'project_id'=>(int)$record['project_id'],'building_id'=>(int)$record['building_id'],
                'revision'=>(int)$record['revision'],'status'=>$record['status'],
                'amount_cents'=>$record['payload']['amount_cents'],'currency'=>'EUR','vat_basis'=>'excluding'];
        }
        return $orders;
    }
}
