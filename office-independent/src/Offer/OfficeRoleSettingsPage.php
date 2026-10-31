<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/** Page composer. Authentication and session routing remain Office router responsibilities. */
final class OfficeRoleSettingsPage
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function render(int $operatorId, string $action, string $csrfToken): string
    {
        if ($csrfToken === '') {
            throw new \DomainException('CSRF token required');
        }
        $roles = (new OfficeRoleSettingsRead($this->db))->rolesForManager($operatorId);
        $html = '<main class="office-settings"><h1>Instellingen / Rollen en rechten</h1>';
        foreach ($roles as $role) {
            $html .= OfficeRoleSettingsView::render(
                [['id' => $role['id'], 'label' => $role['label']]],
                $role['modules'], $action, $csrfToken
            );
        }
        return $html . '</main>';
    }
}
