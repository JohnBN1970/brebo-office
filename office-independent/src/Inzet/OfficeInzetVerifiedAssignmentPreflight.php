<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Read-only preflight for a verified source assignment.
 * This does not import data or write shifts.
 */
final class OfficeInzetVerifiedAssignmentPreflight
{
    private OfficeInzetVerifiedAssignmentResolver $resolver;
    private OfficeInzetIdentityTargetVerifier $targets;
    private OfficeInzetVerifiedAssignmentEligibility $eligibility;

    /**
     * @param list<array{source_system:string,source_person_id:string,office_person_id:int}> $people
     * @param list<array{source_system:string,source_project_id:string,office_project_id:int}> $projects
     */
    public function __construct(PDO $db, array $people, array $projects)
    {
        $this->resolver = new OfficeInzetVerifiedAssignmentResolver($people, $projects);
        $this->targets = new OfficeInzetIdentityTargetVerifier($db);
        $this->eligibility = new OfficeInzetVerifiedAssignmentEligibility($db);
    }

    /** @return array{project_id:int,person_id:int} */
    public function verify(
        string $operatorId,
        string $sourceSystem,
        string $sourceProjectId,
        string $sourcePersonId
    ): array {
        $assignment = $this->resolver->resolve($sourceSystem, $sourceProjectId, $sourcePersonId);
        $this->targets->assertActivePersonAndProject($assignment['person_id'], $assignment['project_id']);
        $this->eligibility->assertManageable($operatorId, $assignment);
        return $assignment;
    }
}
