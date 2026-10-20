<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Read-only preflight of the actual Office target records. A verified mapping
 * is not sufficient when its target has been removed or made inactive.
 */
final class OfficeInzetIdentityTargetVerifier
{
    public function __construct(private PDO $db) {}

    public function assertActivePersonAndProject(int $personId, int $projectId): void
    {
        if ($personId < 1 || $projectId < 1) {
            throw new RuntimeException('Invalid Office identity');
        }
        $person = $this->db->prepare(
            'SELECT 1 FROM office_inzet_people WHERE id = ? AND active = 1 LIMIT 1'
        );
        $person->execute([$personId]);
        if (!$person->fetchColumn()) {
            throw new RuntimeException('Mapped Office person is not active');
        }
        $project = $this->db->prepare(
            'SELECT 1 FROM office_projects WHERE id = ? LIMIT 1'
        );
        $project->execute([$projectId]);
        if (!$project->fetchColumn()) {
            throw new RuntimeException('Mapped Office project does not exist');
        }
    }
}
