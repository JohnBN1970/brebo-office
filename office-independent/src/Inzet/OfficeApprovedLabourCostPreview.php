<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;

require_once __DIR__.'/OfficeApprovedLabourValuation.php';

/**
 * Read-only Office cost preview. Never performs financial posting.
 * A verified rate source is a prerequisite; do not synthesize cost rates
 * from locked budget lines or untrusted request parameters.
 */
final class OfficeApprovedLabourCostPreview
{
    public function __construct(private PDO $db) {}

    public function forApprovedBooking(int $bookingId, array $authorizedRates): array
    {
        if ($bookingId < 1) throw new \InvalidArgumentException('Invalid booking id');
        $q=$this->db->prepare('SELECT id,project_id,person_id,work_date,approved_seconds,source_sha256
            FROM office_inzet_approved_hours WHERE id=?');
        $q->execute([$bookingId]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new \DomainException('Approved hours booking missing');
        foreach (['id','project_id','person_id','approved_seconds'] as $field) {
            if (!isset($row[$field]) || !preg_match('/^[1-9][0-9]*$/D',(string)$row[$field])) {
                throw new \DomainException('Invalid approved-hours ledger evidence');
            }
            $row[$field]=(int)$row[$field];
        }
        return OfficeApprovedLabourValuation::prepare($row,$authorizedRates);
    }
}
