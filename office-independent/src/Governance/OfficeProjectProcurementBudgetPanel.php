<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

use Brebo\Office\Project\OfficeProjectBudgetKpiView;

/**
 * Explicit bridge for an already authenticated project controller.
 * Returns HTML only after all procurement building scopes are authorized.
 */
final class OfficeProjectProcurementBudgetPanel
{
    public function __construct(private readonly OfficeProjectProcurementBudgetService $budgets) {}

    public function render(string $operatorId, int $projectId, array $buildingIds): string
    {
        $comparison=$this->budgets->forProject($operatorId,$projectId,$buildingIds);
        return OfficeProjectBudgetKpiView::render($comparison);
    }
}
