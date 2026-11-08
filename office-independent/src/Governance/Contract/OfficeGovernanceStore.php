<?php
declare(strict_types=1);
namespace Brebo\Office\Governance\Contract;

/** Office-owned identities only. Implementations enforce ACL before returning rows. */
interface OfficeGovernanceStore
{
    public function contexts(string $actor, string $module): array;
    public function allowed(string $actor, string $module, string $permission, int $project, int $building): bool;
    public function ownerAllowed(string $owner, string $module, int $project, int $building): bool;
    public function supplierExists(int $relation): bool;
    public function inspectionFindingExists(int $finding, int $project, int $building): bool;
    public function find(string $module, int $id): ?array;
    public function listing(string $actor, string $module, int $project, int $building): array;
    /** Atomic CAS + command deduplication + append-only audit. Throws on conflict. */
    public function persist(string $module, string $actor, string $command, string $fingerprint, array $record, int $revision): array;
}
