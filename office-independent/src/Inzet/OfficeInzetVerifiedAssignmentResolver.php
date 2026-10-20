<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Resolve verified legacy identities together before any planning operation.
 * This is deliberately read-only: it cannot create personnel, projects or ACLs.
 */
final class OfficeInzetVerifiedAssignmentResolver
{
    /** @var array<string,int> */
    private array $people;
    /** @var array<string,int> */
    private array $projects;

    /**
     * @param list<array{source_system:string,source_person_id:string,office_person_id:int}> $people
     * @param list<array{source_system:string,source_project_id:string,office_project_id:int}> $projects
     */
    public function __construct(array $people, array $projects)
    {
        $this->people = OfficeInzetIdentityMappingGuard::validate($people);
        $this->projects = OfficeInzetProjectIdentityGuard::validate($projects);
    }

    /** @return array{project_id:int,person_id:int} */
    public function resolve(string $sourceSystem, string $sourceProjectId, string $sourcePersonId): array
    {
        if ($sourceSystem === '' || $sourceProjectId === '' || $sourcePersonId === '') {
            throw new InvalidArgumentException('Missing verified assignment identity');
        }
        return [
            'project_id' => OfficeInzetProjectIdentityGuard::requireProject($this->projects, $sourceSystem, $sourceProjectId),
            'person_id' => OfficeInzetIdentityMappingGuard::requireOfficePerson($this->people, $sourceSystem, $sourcePersonId),
        ];
    }
}
