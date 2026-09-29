<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_calculation\Service\CalculationContextService;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a short-lived signed launch token for BREBO Calc.
 *
 * The launch uses BREBO calculation identity/context and no longer requires
 * the calculation to resolve as a Drupal node.
 */
final class CalculationWorkbenchLaunchController implements ContainerInjectionInterface {

  public function __construct(
    private readonly CalculationContextService $calculationContext,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.context'),
      $container->get('current_user'),
    );
  }

  public function launchLegacy(int $node): TrustedRedirectResponse {
    return $this->launch($node);
  }

  public function launch(int $calculation): TrustedRedirectResponse {
    if ($calculation <= 0) {
      throw new NotFoundHttpException('Calculatie niet gevonden.');
    }

    $context = $this->calculationContext->get($calculation);
    if (!$context) {
      throw new NotFoundHttpException('Calculatiecontext niet gevonden.');
    }

    $projectId = (int) ($context['project_id'] ?? 0);
    if ($projectId <= 0) {
      throw new NotFoundHttpException('Geen project gekoppeld aan deze calculatie.');
    }

    $secret = trim((string) getenv('BREBO_CALC_SHARED_SECRET') ?: Settings::get('brebo_calc_shared_secret', ''));
    $baseUrl = rtrim(trim((string) Settings::get('brebo_calc_base_url', getenv('BREBO_CALC_BASE_URL') ?: 'https://calculatie.brebobv.nl')), '/');
    if ($secret === '' || $baseUrl === '') {
      throw new AccessDeniedHttpException('BREBO Calc is niet geconfigureerd.');
    }

    $payload = [
      'v' => 1,
      'calculation_id' => $calculation,
      'project_id' => $projectId,
      'actor_id' => (int) $this->currentUser->id(),
      'exp' => time() + 90,
      'nonce' => bin2hex(random_bytes(16)),
    ];
    $encoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $encoded, $secret);
    $token = $encoded . '.' . $signature;

    return new TrustedRedirectResponse($baseUrl . '/launch?token=' . rawurlencode($token), 302, [
      'Cache-Control' => 'no-store, private',
      'Referrer-Policy' => 'no-referrer',
    ]);
  }

  private function base64UrlEncode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }

}
