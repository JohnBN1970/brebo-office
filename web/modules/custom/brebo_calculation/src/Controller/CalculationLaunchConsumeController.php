<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Owns one-time consumption of Calc launch tokens.
 */
final class CalculationLaunchConsumeController extends ControllerBase {

  public function __construct(
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_calculation.calc_request_authenticator'));
  }

  public function consume(Request $request): JsonResponse {
    $body = $request->getContent();
    $this->authenticator->assertSigned($request, $body);

    $input = json_decode($body, TRUE);
    if (!is_array($input)) {
      return new JsonResponse(['error' => 'Invalid JSON payload.'], 400);
    }

    $nonce = trim((string) ($input['nonce'] ?? ''));
    $expiresAt = (int) ($input['exp'] ?? 0);
    $this->authenticator->claimLaunchNonce($nonce, $expiresAt);

    return new JsonResponse(['consumed' => TRUE]);
  }

}
