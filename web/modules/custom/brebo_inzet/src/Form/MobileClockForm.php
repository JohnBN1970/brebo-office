<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_inzet\Service\PausePolicy;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mobile-first clocking interface for project personnel.
 */
final class MobileClockForm extends FormBase {

  public function __construct(
    private readonly PausePolicy $pausePolicy,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_inzet.pause_policy'));
  }

  public function getFormId(): string {
    return 'brebo_inzet_mobile_clock';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $pauseMode = $node->hasField('field_brebo_pause_mode')
      ? $this->pausePolicy->normalize((string) $node->get('field_brebo_pause_mode')->value)
      : PausePolicy::OFF;

    $form['#attached']['library'][] = 'brebo_inzet/mobile-clock';
    $form['#attributes']['data-brebo-mobile-clock'] = 'true';
    $form['#attributes']['class'][] = 'brebo-mobile-clock';

    $form['project'] = [
      '#markup' => '<div class="brebo-mobile-clock__project"><strong>' . $this->t('Project') . ':</strong> ' . $node->label() . '</div>',
    ];

    $form['location_status'] = [
      '#markup' => '<div class="brebo-mobile-clock__location" data-brebo-clock-location-status>' . $this->t('Locatie voorbereiden…') . '</div>',
    ];

    foreach (['clock_latitude', 'clock_longitude', 'clock_accuracy'] as $name) {
      $form[$name] = ['#type' => 'hidden', '#default_value' => ''];
    }

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['brebo-mobile-clock__actions']],
    ];
    $form['actions']['clock_in'] = [
      '#type' => 'submit',
      '#value' => $this->t('INKLOKKEN'),
      '#name' => 'clock_action',
      '#submit' => ['::submitClockAction'],
      '#attributes' => ['class' => ['button', 'button--primary', 'brebo-mobile-clock__button', 'brebo-mobile-clock__button--in']],
      '#brebo_action' => 'clock_in',
    ];
    $form['actions']['clock_out'] = [
      '#type' => 'submit',
      '#value' => $this->t('UITKLOKKEN'),
      '#name' => 'clock_action',
      '#submit' => ['::submitClockAction'],
      '#attributes' => ['class' => ['button', 'brebo-mobile-clock__button', 'brebo-mobile-clock__button--out']],
      '#brebo_action' => 'clock_out',
    ];

    if ($this->pausePolicy->showsPauseControls($pauseMode)) {
      $form['actions']['pause_start'] = [
        '#type' => 'submit',
        '#value' => $this->t('PAUZE START'),
        '#name' => 'clock_action',
        '#submit' => ['::submitClockAction'],
        '#attributes' => ['class' => ['button', 'brebo-mobile-clock__button', 'brebo-mobile-clock__button--pause']],
        '#brebo_action' => 'pause_start',
      ];
      $form['actions']['pause_end'] = [
        '#type' => 'submit',
        '#value' => $this->t('PAUZE EINDE'),
        '#name' => 'clock_action',
        '#submit' => ['::submitClockAction'],
        '#attributes' => ['class' => ['button', 'brebo-mobile-clock__button', 'brebo-mobile-clock__button--pause']],
        '#brebo_action' => 'pause_end',
      ];
    }

    if ($this->pausePolicy->requiresPauseRegistration($pauseMode)) {
      $form['pause_notice'] = [
        '#markup' => '<p class="brebo-mobile-clock__notice">' . $this->t('Pauzeregistratie is voor dit project verplicht.') . '</p>',
      ];
    }

    $form_state->set('project_id', (int) $node->id());
    $form_state->set('pause_mode', $pauseMode);
    return $form;
  }

  public function submitClockAction(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $action = (string) ($trigger['#brebo_action'] ?? '');
    if (!in_array($action, ['clock_in', 'clock_out', 'pause_start', 'pause_end'], TRUE)) {
      throw new \InvalidArgumentException('Onbekende klokactie.');
    }

    $form_state->set('clock_action', $action);
    $form_state->set('clock_latitude', $form_state->getValue('clock_latitude'));
    $form_state->set('clock_longitude', $form_state->getValue('clock_longitude'));
    $form_state->set('clock_accuracy', $form_state->getValue('clock_accuracy'));

    $this->messenger()->addStatus($this->t('Klokactie @action ontvangen. De opslagkoppeling wordt in de volgende stap aan de beslismotor gehangen.', ['@action' => $action]));
    $form_state->setRebuild();
  }

}
