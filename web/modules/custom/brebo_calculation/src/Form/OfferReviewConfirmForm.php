<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Access\OfferConfirmedAccessCheck;
use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Requires an explicit human acknowledgement for REVIEW calculations. */
final class OfferReviewConfirmForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_calculation.readiness_inspector'),
      $container->get('request_stack'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_offer_review_confirm';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation' || $node->id() === NULL) {
      throw new NotFoundHttpException();
    }

    $calculationId = (int) $node->id();
    $version = $this->latestVersion($calculationId);
    if ($version === '') {
      throw new AccessDeniedHttpException('Calculatieversie ontbreekt.');
    }

    $readiness = $this->readinessInspector->inspect($calculationId, $version);
    $status = (string) ($readiness['status'] ?? 'blocked');
    if ($status === 'blocked') {
      throw new AccessDeniedHttpException('De calculatie bevat blokkerende controlepunten.');
    }

    $form_state->set('calculation_id', $calculationId);
    $form_state->set('calculation_version', $version);

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      'title' => ['#markup' => '<strong>Controle nodig voordat de offerte wordt gemaakt</strong>'],
      'text' => ['#markup' => '<p>De calculatie is niet geblokkeerd, maar bevat waarschuwingen die eerst bewust moeten worden beoordeeld.</p>'],
    ];

    $items = [];
    foreach (($readiness['checks'] ?? []) as $check) {
      if (!is_array($check) || ($check['level'] ?? '') === 'error') {
        continue;
      }
      $items[] = (string) ($check['label'] ?? 'Controlepunt');
    }
    $form['checks'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('@count waarschuwing(en)', ['@count' => count($items)]),
      '#items' => $items,
    ];

    $form['review_confirmed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik heb de waarschuwingen beoordeeld en accepteer dat deze calculatie met REVIEW-status naar de offertefase gaat.'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['continue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Bevestigen en offerte maken'),
      '#button_type' => 'primary',
    ];
    $form['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Terug naar calculatiewerkbank'),
      '#url' => Url::fromRoute('brebo_calculation.workbench', ['node' => $calculationId]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $calculationId = (int) $form_state->get('calculation_id');
    $version = $this->latestVersion($calculationId);
    if ($calculationId <= 0 || $version === '') {
      $form_state->setErrorByName('review_confirmed', $this->t('De actuele calculatieversie kon niet worden vastgesteld.'));
      return;
    }

    $readiness = $this->readinessInspector->inspect($calculationId, $version);
    $status = (string) ($readiness['status'] ?? 'blocked');
    if ($status === 'blocked') {
      $form_state->setErrorByName('review_confirmed', $this->t('De calculatie is inmiddels BLOCKED. Los eerst de blokkerende controlepunten op.'));
      return;
    }

    if ($status === 'review' && !(bool) $form_state->getValue('review_confirmed')) {
      $form_state->setErrorByName('review_confirmed', $this->t('Bevestig dat je de waarschuwingen hebt beoordeeld.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $calculationId = (int) $form_state->get('calculation_id');
    $version = $this->latestVersion($calculationId);
    $readiness = $this->readinessInspector->inspect($calculationId, $version);
    $status = (string) ($readiness['status'] ?? 'blocked');

    $session = $this->requestStack->getCurrentRequest()?->getSession();
    $key = 'brebo_calculation_offer_review_confirmation.' . $calculationId;
    if ($status === 'review') {
      $session?->set($key, [
        'version' => $version,
        'fingerprint' => OfferConfirmedAccessCheck::fingerprint($readiness),
        'confirmed_at' => time(),
        'uid' => (int) $this->currentUser()->id(),
      ]);
    }
    else {
      $session?->remove($key);
    }

    $form_state->setRedirect('brebo_calculation.offer_create_internal', ['node' => $calculationId]);
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
