<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use DomainException;
use PDO;

/**
 * Application boundary for Calc -> Office quotation generation.
 *
 * The caller must authenticate the Office user and validate POST CSRF.
 * $resolveAuthorizedCalculation MUST read the Calc revision server-side,
 * authorize the operator and return the CRM relation and sales snapshot.
 * Never pass client-submitted prices or relation IDs into the resolver.
 */
final class OfficeQuotationCalcGenerationService
{
    public function __construct(private readonly PDO $db) {}

    public function generate(
        int $operatorId,
        array $intent,
        callable $resolveAuthorizedCalculation
    ): int {
        if ($operatorId < 1) throw new DomainException('Authenticated operator required');
        require_once __DIR__ . '/OfficeQuotationCalcLaunchContract.php';
        require_once __DIR__ . '/OfficeQuotationCalcDraftFactory.php';
        $request = OfficeQuotationCalcLaunchContract::validateIntent($intent);
        $authorized = $resolveAuthorizedCalculation(
            $operatorId, $request['calculation_id'], $request['calculation_version']
        );
        if (!is_array($authorized)) throw new DomainException('Calc access denied');
        $relationId = $authorized['relation_id'] ?? null;
        $title = $authorized['title'] ?? null;
        $opportunityId = $authorized['opportunity_id'] ?? null;
        $snapshot = $authorized['sales_snapshot'] ?? null;
        if (!is_int($opportunityId) || $opportunityId < 1
            || !is_int($relationId) || $relationId < 1
            || !is_string($title) || trim($title) === ''
            || !is_array($snapshot)
            || ($snapshot['calculation_id'] ?? null) !== $request['calculation_id']
            || ($snapshot['version'] ?? null) !== $request['calculation_version']) {
            throw new DomainException('Verified Calc revision or CRM context missing');
        }
        // Fail closed if the resolver returns a different calculation context.
        // A matching version alone is not sufficient when multiple Calc drafts exist.
        if (isset($authorized['calculation_id'])
            && $authorized['calculation_id'] !== $request['calculation_id']) {
            throw new DomainException('Authorized calculation identity mismatch');
        }
        if (isset($authorized['calculation_version'])
            && $authorized['calculation_version'] !== $request['calculation_version']) {
            throw new DomainException('Authorized calculation version mismatch');
        }
        // The opportunity must belong to the verified relation. Never trust a
        // browser-supplied CRM association or a resolver without this check.
        $opportunity = $this->db->prepare(
            'SELECT id FROM office_crm_opportunities WHERE id = ? AND relation_id = ? LIMIT 1'
        );
        $opportunity->execute([$opportunityId, $relationId]);
        if ($opportunity->fetchColumn() === false) {
            throw new DomainException('CRM opportunity does not belong to calculation relation');
        }
        $type = $authorized['quotation_type'] ?? 'project';
        if (!is_string($type)) throw new DomainException('Invalid quotation template');
        $bind = null;
        if (getenv('OFFICE_QUOTATION_COST_BINDING_ENABLED') === 'enabled') {
            require_once __DIR__.'/OfficeQuotationBudgetBinding.php';
            $bind = static function(PDO $db,int $id,array $prepared) use($operatorId,$request,$opportunityId,$resolveAuthorizedCalculation):void {
                (new OfficeQuotationBudgetBinding($db))->bind($id,$request['calculation_id'],$operatorId,$opportunityId,$prepared,$resolveAuthorizedCalculation);
            };
        }
        return (new OfficeQuotationCalcDraftFactory($this->db))->create(
            $relationId, $operatorId, $title, $snapshot, $type, $opportunityId, $bind
        );
    }

    public static function editorUrl(int $quotationId): string
    {
        if ($quotationId < 1) throw new DomainException('Invalid quotation ID');
        return '/office-test/crm/offertes?edit=' . $quotationId;
    }
}
