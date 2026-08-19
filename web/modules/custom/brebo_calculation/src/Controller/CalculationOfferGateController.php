<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Form\OfferReviewConfirmForm;
use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Routes offer creation through READY / REVIEW / BLOCKED readiness. */
final class CalculationOfferGateController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly FormBuilderInterface $formBuilderService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_calculation.readiness_inspector'),
      $container->get('form_builder'),
    );
  }

  /**
   * READY goes straight through, REVIEW requires confirmation, BLOCKED stops.
   */
  public function gate(NodeInterface $node): array|RedirectResponse {
    if ($node->bundle() !== 'brebo_calculation' || $node->id() === NULL) {
      throw new NotFoundHttpException();
    }

    $version = $this->latestVersion((int) $node->id());
    if ($version === '') {
      throw new AccessDeniedHttpException('Calculatieversie ontbreekt.');
    }

    $readiness = $this->readinessInspector->inspect((int) $node->id(), $version);
    $status = (string) ($readiness['status'] ?? 'blocked');
    if ($status === 'blocked') {
      throw new AccessDeniedHttpException('De calculatie bevat blokkerende controlepunten.');
    }

    if ($status === 'ready') {
      $url = Url::fromRoute('brebo_calculation.offer_create_internal', ['node' => (int) $node->id()]);
      return new RedirectResponse($url->toString());
    }

    return $this->formBuilderService->getForm(OfferReviewConfirmForm::class, $node);
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

}
