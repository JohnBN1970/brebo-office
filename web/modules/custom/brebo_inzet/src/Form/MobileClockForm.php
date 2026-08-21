<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\brebo_inzet\Service\ClockSessionManager;
use Drupal\brebo_inzet\Service\PausePolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mobile-first clocking interface for project personnel.
 */
final class MobileClockForm extends FormBase {

  public function __construct(
    private PausePolicy $pausePolicy,
    private ClockSessionManager $clockSessionManager,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_inzet.pause_policy'),
      $container->get('brebo_inzet.clock_session_manager'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_inzet_mobile_clock';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $userId = (int) $this->currentUser()->id();
    $pauseMode = $node->hasField('field_brebo_pause_mode')
      ? $this->pausePolicy->normalize((string) $node->get('field_brebo_pause_mode')->value)
      : PausePolicy::OFF;
    $openForUser = $this->clockSessionManager->findOpenForUser($userId);
    $open = $this->clockSessionManager->findOpen($node, $userId);

    $form['#attached']['library'][] = 'brebo_inzet/mobile-clock';
    $form['#attributes']['data-brebo-mobile-clock'] = 'true';
    $form['#attributes']['class'][] = 'brebo-mobile-clock';
    $form['project'] = ['#markup' => '<div class="brebo-mobile-clock__project"><strong>' . $this->t('Project') . ':</strong> ' . $node->label() . '</div>'];

    if ($openForUser instanceof NodeInterface) {
      $activeProjectId = (int) ($openForUser->get('field_brebo_project_ref')->target_id ?? 0);
      $activeProject = $activeProjectId > 0 ? $this->entityTypeManager->getStorage('node')->load($activeProjectId) : NULL;
      $activeLabel = $activeProject instanceof NodeInterface ? $activeProject->label() : $this->t('Onbekend project');
      $clockInValue = (string) $openForUser->get('field_brebo_clock_in')->value;
      $clockIn = $clockInValue !== '' ? new \DateTimeImmutable($clockInValue) : NULL;
      $duration = $clockIn ? $this->formatDuration($clockIn, new \DateTimeImmutable('now')) : $this->t('onbekend');
      $since = $clockIn ? $clockIn->format('H:i') : '-';
      $form['session_status'] = [
        '#markup' => '<div class="brebo-mobile-clock__session"><strong>' . $this->t('Status') . ':</strong> ' . $this->t('Ingeklokt op @project sinds @time (@duration)', ['@project' => $activeLabel, '@time' => $since, '@duration' => $duration]) . '</div>',
      ];

      if (!$open && $activeProject instanceof NodeInterface) {
        $form['active_project'] = [
          '#type' => 'link',
          '#title' => $this->t('NAAR ACTIEF PROJECT'),
          '#url' => Url::fromRoute('brebo_inzet.mobile_clock', ['node' => (int) $activeProject->id()]),
          '#attributes' => ['class' => ['button', 'button--primary', 'brebo-mobile-clock__button']],
        ];
      }
    }
    else {
      $form['session_status'] = ['#markup' => '<div class="brebo-mobile-clock__session"><strong>' . $this->t('Status') . ':</strong> ' . $this->t('Uitgeklokt') . '</div>'];
    }

    $form['location_status'] = ['#markup' => '<div class="brebo-mobile-clock__location" data-brebo-clock-location-status>' . $this->t('Locatie voorbereiden…') . '</div>'];

    foreach (['clock_latitude', 'clock_longitude', 'clock_accuracy'] as $name) {
      $form[$name] = ['#type' => 'hidden', '#default_value' => ''];
    }
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reden / toelichting'),
      '#description' => $this->t('Alleen nodig wanneer BREBO Inzet een afwijking constateert.'),
      '#rows' => 2,
    ];

    $form['actions'] = ['#type' => 'actions', '#attributes' => ['class' => ['brebo-mobile-clock__actions']]];
    if ($openForUser === NULL) {
      $form['actions']['clock_in'] = [
        '#type' => 'submit', '#value' => $this->t('INKLOKKEN'), '#name' => 'clock_action', '#submit' => ['::submitClockAction'],
        '#attributes' => ['class' => ['button', 'button--primary', 'brebo-mobile-clock__button', 'brebo-mobile-clock__button--in']], '#brebo_action' => 'clock_in',
      ];
    }
    elseif ($open) {
      $form['actions']['clock_out'] = [
        '#type' => 'submit', '#value' => $this->t('UITKLOKKEN'), '#name' => 'clock_action', '#submit' => ['::submitClockAction'],
        '#attributes' => ['class' => ['button', 'brebo-mobile-clock__button', 'brebo-mobile-clock__button--out']], '#brebo_action' => 'clock_out',
      ];
    }

    if ($open && $this->pausePolicy->showsPauseControls($pauseMode)) {
      $form['actions']['pause_start'] = ['#type' => 'submit', '#value' => $this->t('PAUZE START'), '#name' => 'clock_action', '#submit' => ['::submitClockAction'], '#brebo_action' => 'pause_start'];
      $form['actions']['pause_end'] = ['#type' => 'submit', '#value' => $this->t('PAUZE EINDE'), '#name' => 'clock_action', '#submit' => ['::submitClockAction'], '#brebo_action' => 'pause_end'];
    }
    if ($this->pausePolicy->requiresPauseRegistration($pauseMode)) {
      $form['pause_notice'] = ['#markup' => '<p class="brebo-mobile-clock__notice">' . $this->t('Pauzeregistratie is voor dit project verplicht.') . '</p>'];
    }

    $form_state->set('project_id', (int) $node->id());
    return $form;
  }

  /**
   * Default submit handler required by FormInterface.
   *
   * All clock buttons deliberately use submitClockAction() as their explicit
   * submit handler, so the default handler is intentionally a no-op.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function submitClockAction(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $action = (string) ($trigger['#brebo_action'] ?? '');
    $project = $this->entityTypeManager->getStorage('node')->load((int) $form_state->get('project_id'));
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $lat = $this->nullableFloat($form_state->getValue('clock_latitude'));
    $lng = $this->nullableFloat($form_state->getValue('clock_longitude'));
    $accuracy = $this->nullableFloat($form_state->getValue('clock_accuracy'));
    $userId = (int) $this->currentUser()->id();

    try {
      if ($action === 'clock_in') {
        $result = $this->clockSessionManager->clockIn($project, $userId, $lat, $lng, $accuracy);
        $location = (string) ($result['location']['status'] ?? 'Onbekend');
        $this->messenger()->addStatus($this->t('Ingeklokt. Locatiecontrole: @location.', ['@location' => $location]));
      }
      elseif ($action === 'clock_out') {
        $result = $this->clockSessionManager->clockOut($project, $userId, $lat, $lng, $accuracy, (string) $form_state->getValue('reason'));
        if (!empty($result['requires_reason'])) {
          $this->messenger()->addWarning($this->t('Deze uitklokactie wijkt af: @message Vul een reden in en druk opnieuw op UITKLOKKEN.', ['@message' => (string) ($result['verdict']['message'] ?? '')]));
        }
        else {
          $this->messenger()->addStatus($this->t('Uitgeklokt: @status.', ['@status' => (string) ($result['verdict']['status'] ?? 'geregistreerd')]));
        }
      }
      else {
        $this->messenger()->addWarning($this->t('Pauzeregistratie is zichtbaar volgens het projectbeleid, maar wordt in de volgende slice als eigen gebeurtenis opgeslagen.'));
      }
    }
    catch (\InvalidArgumentException $exception) {
      $this->messenger()->addError($exception->getMessage());
    }

    $form_state->setRebuild();
  }

  private function nullableFloat(mixed $value): ?float {
    return $value === NULL || $value === '' ? NULL : (float) $value;
  }

  private function formatDuration(\DateTimeImmutable $from, \DateTimeImmutable $to): string {
    $seconds = max(0, $to->getTimestamp() - $from->getTimestamp());
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return sprintf('%d:%02d uur', $hours, $minutes);
  }

}
