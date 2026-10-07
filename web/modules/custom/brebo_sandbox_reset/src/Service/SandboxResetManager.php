<?php

declare(strict_types=1);

namespace Drupal\brebo_sandbox_reset\Service;

use Drupal\brebo_sandbox_reset\Contract\SandboxResetGatewayInterface;

/** Safely orchestrates sandbox resets while preserving software and configuration. */
final class SandboxResetManager {

  private const ALLOWED_SCOPES = [
    'mail_content',
    'mail_content_zoho',
    'projects',
    'buildings',
    'projects_buildings',
    'website_europakozijn',
  ];

  public function __construct(
    private readonly SandboxResetGatewayInterface $gateway,
  ) {}

  /** @return array<string, int|string> */
  public function preview(string $scope): array {
    $this->assertAllowedScope($scope);
    return $this->gateway->preview($scope);
  }

  /** @return array<string, int|string> */
  public function reset(string $scope): array {
    $this->assertAllowedScope($scope);
    $preview = $this->gateway->preview($scope);

    $this->gateway->transactional(function () use ($scope): void {
      if (str_starts_with($scope, 'mail_content')) {
        $this->gateway->resetMail($scope);
      }
      elseif ($scope === 'website_europakozijn') {
        $this->gateway->resetWebsiteEuropakozijn();
      }
      else {
        $this->gateway->resetObjects($scope);
      }
    });

    return $preview + ['result' => 'completed'];
  }

  private function assertAllowedScope(string $scope): void {
    if (!in_array($scope, self::ALLOWED_SCOPES, TRUE)) {
      throw new \InvalidArgumentException('Onbekende sandbox-resetscope.');
    }
  }

}
