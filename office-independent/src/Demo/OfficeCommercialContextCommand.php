<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use DomainException;
use InvalidArgumentException;

/** POST application command. Route must authenticate session and check Origin/HTTPS. */
final class OfficeCommercialContextCommand {
    public static function csrfToken(string $sessionId, string $secret): string {
        if ($sessionId === '' || strlen($secret) < 32) throw new InvalidArgumentException('CSRF configuration missing');
        return hash_hmac('sha256', 'crm-commercial-context:' . $sessionId, $secret);
    }

    public static function handle(
        WebsiteCrmOperatorContext $operator,
        string $csrf,
        string $secret,
        array $input,
        OfficeCommercialContextWriter $writer
    ): void {
        $operator->requireCrmEdit();
        if (!hash_equals(self::csrfToken($operator->sessionId, $secret), $csrf)) {
            throw new DomainException('Invalid CSRF token');
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
        $writer->save(true, $type, $id, $position, $categories, $scope, $profileId);
    }
}
