<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Building-scoped CL-204 draft write service.
 * Host must authenticate operator and verify CSRF before invoking this service.
 * Project membership is checked again in the same database transaction.
 */
final class OfficeCl204BuildingDraftService
{
    public function __construct(private readonly PDO $db) {}

    public function save(int $buildingId, int $operatorId, array $post, string $reason, ?int $formId = null, ?int $expectedRevision = null): array
    {
        if ($buildingId < 1 || $operatorId < 1 || trim($reason) === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Ongeldige opnamecontext');
        }
        require_once __DIR__.'/OfficeCl204DraftInput.php';
        require_once __DIR__.'/OfficeProcessFormRepository.php';
        $this->db->beginTransaction();
        try {
            // Only a manage membership on a project linked to this building permits writes.
            $access = $this->db->prepare(
                "SELECT 1 FROM office_project_buildings pb
                 JOIN office_project_memberships m ON m.project_id=pb.project_id
                 JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
                 WHERE pb.building_id=? AND m.operator_id=? AND m.permission='manage' LIMIT 1"
            );
            $access->execute([$buildingId,$operatorId]);
            if (!$access->fetchColumn()) throw new RuntimeException('Geen schrijfrechten op dit gebouw');

            $repository = new OfficeProcessFormRepository($this->db);
            $actor = (string)$operatorId;
            if ($formId === null) {
                if ($expectedRevision !== null) throw new InvalidArgumentException('Onverwachte revisie');
                $data = OfficeCl204DraftInput::parse($post);
                // Repository owns its own transaction; close the authorization transaction
                // only after access check. A concurrent permission change must be checked
                // by the host's authorization layer before any request reaches this service.
                $this->db->commit();
                $id = $repository->create('CL-204','1.0','building',$buildingId,$actor,$data,$reason);
                return ['id'=>$id,'revision'=>1];
            }
            if ($formId < 1 || $expectedRevision === null || $expectedRevision < 1) {
                throw new InvalidArgumentException('Ongeldige revisie');
            }
            $stored = $repository->loadBuildingOpname($buildingId,$formId);
            if ($stored === null || $stored['status'] !== 'concept') {
                throw new RuntimeException('Conceptopname niet gevonden');
            }
            if ((int)$stored['revision'] !== $expectedRevision) {
                throw new RuntimeException('Opname is inmiddels gewijzigd');
            }
            $data = OfficeCl204DraftInput::parse($post,$stored['data']);
            $this->db->commit();
            $revision = $repository->revise($formId,$expectedRevision,$actor,$data,$reason);
            return ['id'=>$formId,'revision'=>$revision];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
