<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use DomainException;
use PDO;

/**
 * Authenticated Office-router adapter, intentionally not a public entrypoint.
 * Router must pass the server-side session operator ID and CSRF secret.
 */
final class OfficeRoleSettingsEndpoint
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function handle(string $method, int $operatorId, string $csrfToken, string $actionUrl, array $post = []): array
    {
        if ($operatorId < 1 || $csrfToken === '') {
            return ['status' => 403, 'body' => 'Geen toegang'];
        }
        try {
            if ($method === 'GET') {
                return ['status' => 200, 'body' => (new OfficeRoleSettingsPage($this->db))
                    ->render($operatorId, $actionUrl, $csrfToken)];
            }
            if ($method === 'POST') {
                (new OfficeRoleSettingsController($this->db))->save($operatorId, $post, $csrfToken);
                return ['status' => 303, 'location' => $actionUrl, 'body' => ''];
            }
            return ['status' => 405, 'body' => 'Methode niet toegestaan'];
        } catch (DomainException $exception) {
            return ['status' => 403, 'body' => 'Geen toegang of ongeldige instellingen'];
        }
    }
}
