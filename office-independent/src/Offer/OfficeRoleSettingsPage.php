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
        $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Rollen en rechten · BREBO Office</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:0;background:#f4f6fa;color:#182638}'
            . 'main{max-width:1050px;margin:2rem auto;padding:1.5rem}'
            . 'section{background:white;border:1px solid #dce2ea;border-radius:12px;padding:1.5rem;margin:1rem 0}'
            . 'fieldset{border:1px solid #dce2ea;border-radius:8px;padding:1rem;margin:1rem 0}'
            . 'label{display:block;margin:.7rem 0}button{padding:.65rem 1.2rem;cursor:pointer}'
            . 'details{margin:1rem 0}select{margin-left:.6rem}</style></head><body>'
            . '<main class="office-settings">';
        if ($roles === []) {
            $html .= '<section><h1>Rollen en rechten</h1><p>Er zijn nog geen actieve rollen ingericht. Er is niets gewijzigd.</p></section>';
        }
        foreach ($roles as $role) {
            $html .= OfficeRoleSettingsView::render(
                [['id' => $role['id'], 'label' => $role['label']]],
                $role['modules'], $action, $csrfToken
            );
        }
        return $html . '</main></body></html>';
    }
}
