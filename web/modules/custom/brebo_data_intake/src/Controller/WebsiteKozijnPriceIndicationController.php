<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Controller;

use Drupal\brebo_calculation\Domain\CalculationParameters;
use Drupal\brebo_calculation\Service\KozijnCommercialPriceService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Authenticated public-safe kozijn price indication for brebo-platform. */
final class WebsiteKozijnPriceIndicationController extends ControllerBase {

  public function __construct(private readonly KozijnCommercialPriceService $priceService) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_calculation.kozijn_commercial_price'));
  }

  public function calculate(Request $request): JsonResponse {
    $raw = $request->getContent();
    if (!$this->authenticated($request, $raw)) {
      return $this->error(401, 'invalid_signature');
    }

    try {
      $payload = json_decode($raw, TRUE, 64, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return $this->error(400, 'invalid_json');
    }

    if (!is_array($payload) || ($payload['schema_version'] ?? NULL) !== '1.0' || !is_array($payload['configuration'] ?? NULL)) {
      return $this->error(422, 'invalid_payload');
    }

    // Commercial policy is Office-owned and fail-closed. A public amount may
    // only leave Office after an explicit commercial policy is configured.
    $policy = Settings::get('kozijn_public_price_policy');
    if (!is_array($policy) || ($policy['enabled'] ?? NULL) !== TRUE) {
      return $this->indicationUnavailable('commercial_policy_not_configured');
    }

    $method = $policy['commercial_method'] ?? NULL;
    if (!is_string($method) || !in_array($method, ['tail_costs', 'single_margin'], TRUE)) {
      return $this->indicationUnavailable('commercial_policy_invalid');
    }

    $required = $method === 'single_margin'
      ? ['single_margin_pct']
      : ['general_cost_pct', 'risk_pct', 'profit_pct'];
    foreach ($required as $key) {
      if (!array_key_exists($key, $policy) || !is_numeric($policy[$key])) {
        return $this->indicationUnavailable('commercial_policy_invalid');
      }
      $value = (float) $policy[$key];
      if (!is_finite($value) || $value < 0.0) {
        return $this->indicationUnavailable('commercial_policy_invalid');
      }
    }
    if (!array_key_exists('commercial_adjustment', $policy) || !is_numeric($policy['commercial_adjustment'])) {
      return $this->indicationUnavailable('commercial_policy_invalid');
    }
    $adjustment = (float) $policy['commercial_adjustment'];
    if (!is_finite($adjustment)) {
      return $this->indicationUnavailable('commercial_policy_invalid');
    }

    // Forward only parameters active for the selected method. Stale inactive
    // settings must never turn a safe public request into an exception.
    $parameters = new CalculationParameters(
      commercialMethod: $method,
      generalCostPct: $method === 'tail_costs' ? (float) $policy['general_cost_pct'] : 0.0,
      riskPct: $method === 'tail_costs' ? (float) $policy['risk_pct'] : 0.0,
      profitPct: $method === 'tail_costs' ? (float) $policy['profit_pct'] : 0.0,
      singleMarginPct: $method === 'single_margin' ? (float) $policy['single_margin_pct'] : 0.0,
      commercialAdjustment: $adjustment,
    );
    $result = $this->priceService->calculate($payload['configuration'], $parameters);
    $public = $result['public'] ?? [
      'status' => 'insufficient_calibration',
      'model_version' => $result['model_version'] ?? NULL,
      'message' => 'Voor deze configuratie is nog geen betrouwbare prijsindicatie beschikbaar.',
    ];

    $response = new JsonResponse(['status' => 'ok', 'indication' => $public], 200);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  private function authenticated(Request $request, string $raw): bool {
    $secret = trim((string) Settings::get('brebo_shared_secret', getenv('BREBO_SHARED_SECRET') ?: ''));
    $timestamp = $request->headers->get('X-BREBO-Timestamp', '');
    $requestId = $request->headers->get('X-BREBO-Request-Id', '');
    $signatureHeader = $request->headers->get('X-BREBO-Signature', '');

    if ($secret === '' || !preg_match('/^[0-9]+$/', $timestamp) || !preg_match('/^[0-9a-f-]{36}$/i', $requestId) || !preg_match('/^v1=([a-f0-9]{64})$/', $signatureHeader, $match)) {
      return FALSE;
    }
    if (abs(time() - (int) $timestamp) > 300) {
      return FALSE;
    }

    $bodyHash = hash('sha256', $raw);
    $canonical = implode("\n", ['POST', $request->getPathInfo(), $bodyHash, $timestamp, $requestId]);
    return hash_equals(hash_hmac('sha256', $canonical, $secret), $match[1]);
  }

  private function indicationUnavailable(string $reason): JsonResponse {
    $response = new JsonResponse([
      'status' => 'ok',
      'indication' => [
        'status' => 'temporarily_unavailable',
        'reason' => $reason,
      ],
    ], 200);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  private function error(int $status, string $code): JsonResponse {
    $response = new JsonResponse(['status' => 'error', 'error' => ['code' => $code]], $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}
