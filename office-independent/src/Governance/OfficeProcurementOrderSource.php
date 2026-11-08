<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

/** Canonical read contract for Finance/project consumers. Never writes a second ledger. */
final class OfficeProcurementOrderSource
{
    public function __construct(private readonly OfficeGovernanceService $service) {}
    /** Values are EUR cents excluding VAT; a concept or approval is not yet an awarded obligation. */
    /** Aggregate awarded procurement for a native project across its buildings.
     *  Each record is returned once; draft/submitted/approved values are excluded.
     *  Requires explicit procurement/read permission per building context.
     */
    public function awardedProjectOrders(string $actor, int $project, array $buildingIds): array
    {
        if ($project < 1 || $buildingIds === []) throw new \InvalidArgumentException('Project en gebouwscope vereist.');
        $seen = [];
        $all = [];
        foreach ($buildingIds as $buildingId) {
            $building = filter_var($buildingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($building)) throw new \InvalidArgumentException('Ongeldig gebouw.');
            if (isset($seen[$building])) continue;
            $seen[$building] = true;
            foreach ($this->awardedOrders($actor, $project, $building) as $order) {
                $key = $order['source'] . ':' . $order['order_id'];
                $all[$key] = $order;
            }
        }
        return array_values($all);
    }

    /** Total value of placed orders, not a booked cost, supplier invoice or bank payment. */
    public function awardedProjectCommitment(string $actor, int $project, array $buildingIds): array
    {
        $orders = $this->awardedProjectOrders($actor, $project, $buildingIds);
        $total = 0;
        foreach ($orders as $order) {
            $amount = $order['amount_cents'];
            if (!is_int($amount) || $amount < 0 || $total > PHP_INT_MAX - $amount) {
                throw new \OverflowException('Ongeldige inkoopverplichting.');
            }
            $total += $amount;
        }
        return ['source' => 'office_procurement_orders', 'project_id' => $project,
            'currency' => 'EUR', 'vat_basis' => 'excluding', 'awarded_order_count' => count($orders),
            'commitment_cents' => $total, 'orders' => $orders];
    }

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
