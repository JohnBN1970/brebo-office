<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a short-lived signed launch token for BREBO Calc.
 */
final class CalculationWorkbenchLaunchController {

  public function launch(NodeInterface $node): TrustedRedirectResponse {
    if ($node->bundle() !== 'brebo_calculation') {
      throw new NotFoundHttpException();
    }

    $package = $node->hasField('field_brebo_package_ref') ? $node->get('field_brebo_package_ref')->entity : NULL;
    if (!$package instanceof NodeInterface || !$package->hasField('field_brebo_project_ref') || $package->get('field_brebo_project_ref')->isEmpty()) {
      throw new NotFoundHttpException('Geen project gekoppeld aan deze calculatie.');
    }

    $projectId = (int) $package->get('field_brebo_project_ref')->target_id;
    $secret = trim((string) Settings::get('brebo_calc_shared_secret', getenv('BREBO_CALC_SHARED_SECRET') ?: ''));
    $baseUrl = rtrim(trim((string) Settings::get('brebo_calc_base_url', getenv('BREBO_CALC_BASE_URL') ?: 'https://calculatie.brebobv.nl')), '/');
    if ($secret === '' || $baseUrl === '') {
      throw new AccessDeniedHttpException('BREBO Calc is niet geconfigureerd.');
    }

    $payload = [
      'v' => 1,
      'calculation_id' => (int) $node->id(),
      'project_id' => $projectId,
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
