<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Access;

use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Protects the actual offer form behind the current readiness decision. */
final class OfferConfirmedAccessCheck implements AccessInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly RequestStack $requestStack,
  ) {}

  public function access(NodeInterface $node): AccessResult {
    if ($node->bundle() !== 'brebo_calculation' || $node->id() === NULL) {
      return AccessResult::forbidden('Offer readiness applies only to calculations.');
    }

    $version = $this->latestVersion((int) $node->id());
    if ($version === '') {
      return AccessResult::forbidden('A calculation version is required before an offer can be created.')
        ->addCacheableDependency($node)
        ->setCacheMaxAge(0);
    }

    $readiness = $this->readinessInspector->inspect((int) $node->id(), $version);
    $status = (string) ($readiness['status'] ?? 'blocked');
    if ($status === 'blocked') {
      return AccessResult::forbidden('The calculation contains blocking readiness findings.')
        ->addCacheableDependency($node)
        ->setCacheMaxAge(0);
    }

    if ($status === 'ready') {
      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->setCacheMaxAge(0);
    }

    $session = $this->requestStack->getCurrentRequest()?->getSession();
    $confirmation = $session?->get('brebo_calculation_offer_review_confirmation.' . $node->id());
    $allowed = is_array($confirmation)
      && ($confirmation['version'] ?? '') === $version
      && ($confirmation['fingerprint'] ?? '') === self::fingerprint($readiness);

    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($node)
      ->setCacheMaxAge(0);
  }

  private function latestVersion(int $calculationId): string {
    $version = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version'])
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return is_string($version) ? $version : '';
  }

  /** Creates a stable fingerprint for the exact current warning set. */
  public static function fingerprint(array $readiness): string {
    $checks = array_map(
      static fn (array $check): array => [
        'level' => (string) ($check['level'] ?? ''),
        'code' => (string) ($check['code'] ?? ''),
        'reference' => (int) ($check['reference'] ?? 0),
        'label' => (string) ($check['label'] ?? ''),
      ],
      array_values(array_filter($readiness['checks'] ?? [], 'is_array')),
    );
    usort($checks, static fn (array $a, array $b): int => json_encode($a) <=> json_encode($b));
    return hash('sha256', json_encode([
      'status' => (string) ($readiness['status'] ?? ''),
      'blocking' => (int) ($readiness['blocking'] ?? 0),
      'warnings' => (int) ($readiness['warnings'] ?? 0),
      'checks' => $checks,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

}
