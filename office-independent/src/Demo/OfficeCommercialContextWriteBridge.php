<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use InvalidArgumentException;

/** One guarded entrypoint for CRM writes; HTTP route must verify HTTPS and Origin. */
final class OfficeCommercialContextWriteBridge {
    public function __construct(
        private OfficeCommercialContextWriter $writer,
        private OfficeCommercialContextIntegrity $integrity,
    ) {}

    public function save(
        WebsiteCrmOperatorContext $operator,
        string $csrf,
        string $secret,
        array $input,
        callable $authorizedSourceLookup
    ): void {
        $operator->requireCrmEdit();
        if (!hash_equals(OfficeCommercialContextCommand::csrfToken($operator->sessionId, $secret), $csrf)) {
            throw new \DomainException('Invalid CSRF token');
        }
        $type = $input['source_type'] ?? null;
        $id = $input['source_id'] ?? null;
        $position = $input['commercial_position'] ?? null;
        $categories = $input['work_categories'] ?? null;
        $scope = $input['work_scope'] ?? null;
        $profileId = $input['profile_id'] ?? null;
        if (!is_string($type) || !is_string($id) || !is_string($position) || !is_array($categories)
            || ($scope !== null && !is_string($scope))
            || ($profileId !== null && (!is_int($profileId) || $profileId < 1))) {
            throw new InvalidArgumentException('Invalid CRM commercial input');
        }
        OfficeCommercialContextIntegrity::assertSource($type, $id, $authorizedSourceLookup);
        $this->integrity->assertProfile($profileId, $position);
        $this->writer->save(true, $type, $id, $position, $categories, $scope, $profileId);
    }
}
