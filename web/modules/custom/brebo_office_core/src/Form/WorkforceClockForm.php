<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\WorkforceGeoFence;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mobile-first clocking form for BREBO Inzet shifts.
 */
final class WorkforceClockForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforceGeoFence $geoFence,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.workforce_geofence'),
    );
  }

  public function getFormId(): string {
    return 'brebo_office_core_workforce_clock_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node || $node->bundle() !== 'brebo_shift') {
      throw new \InvalidArgumentException('Deze klokpagina vereist een BREBO Inzet-dienst.');
    }

    $form_state->set('shift_id', (int) $node->id());
    $building = $node->get('field_brebo_shift_building')->entity;
    $activity = $node->get('field_brebo_shift_activity')->entity;
    $last = $this->lastClockEvent($node);

    $form['#attributes']['class'][] = 'brebo-inzet-clock';
    $form['#attached']['library'][] = 'brebo_office/workforce-clock-location';

    $form['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-inzet-clock__summary']],
      'title' => ['#markup' => '<h2>' . $this->t('Klokken voor @shift', ['@shift' => $node->label()]) . '</h2>'],
      'building' => ['#markup' => '<p><strong>' . $this->t('Locatie') . ':</strong> ' . ($building?->label() ?? '—') . '</p>'],
      'activity' => ['#markup' => '<p><strong>' . $this->t('Werkzaamheden') . ':</strong> ' . ($activity?->label() ?? '—') . '</p>'],
      'last' => ['#markup' => '<p><strong>' . $this->t('Laatste klokactie') . ':</strong> ' . ($last ? $last->get('field_brebo_clock_type')->value . ' · ' . $last->get('field_brebo_clock_at')->value : $this->t('Nog niet geklokt')) . '</p>'],
    ];

    $form['latitude'] = ['#type' => 'hidden', '#attributes' => ['data-brebo-clock-lat' => '1']];
    $form['longitude'] = ['#type' => 'hidden', '#attributes' => ['data-brebo-clock-lon' => '1']];
    $form['accuracy'] = ['#type' => 'hidden', '#attributes' => ['data-brebo-clock-accuracy' => '1']];
    $form['location_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Locatiecontrole'),
      '#markup' => '<span data-brebo-clock-location-status>' . $this->t('Locatie wordt bij de klokactie eenmalig bepaald.') . '</span>',
    ];
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Toelichting bij afwijking'),
      '#description' => $this->t('Verplicht wanneer locatie niet beschikbaar is of buiten de ingestelde zone valt.'),
      '#rows' => 3,
    ];

    $allowed = $this->allowedActions($last?->get('field_brebo_clock_type')->value);
    $labels = [
      'In' => $this->t('Inklokken'),
      'Pauze start' => $this->t('Pauze starten'),
      'Pauze einde' => $this->t('Pauze beëindigen'),
      'Uit' => $this->t('Uitklokken'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    foreach ($allowed as $action) {
      $form['actions'][$this->actionKey($action)] = [
        '#type' => 'submit',
        '#value' => $labels[$action],
        '#name' => 'clock_action',
        '#submit' => ['::submitClock'],
        '#brebo_clock_type' => $action,
        '#attributes' => ['class' => ['button', $action === 'In' || $action === 'Uit' ? 'button--primary' : '']],
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // All clock actions use submitClock().
  }

  public function submitClock(array &$form, FormStateInterface $form_state): void {
    $shift = $this->entityTypeManager->getStorage('node')->load((int) $form_state->get('shift_id'));
    if (!$shift instanceof NodeInterface || $shift->bundle() !== 'brebo_shift') {
      $form_state->setErrorByName('', $this->t('De dienst kon niet worden geladen.'));
      return;
    }

    $trigger = $form_state->getTriggeringElement();
    $type = (string) ($trigger['#brebo_clock_type'] ?? '');
    $last = $this->lastClockEvent($shift);
    if (!in_array($type, $this->allowedActions($last?->get('field_brebo_clock_type')->value), TRUE)) {
      $form_state->setErrorByName('', $this->t('Deze klokactie past niet bij de huidige klokstatus. Vernieuw de pagina en probeer opnieuw.'));
      return;
    }

    $building = $shift->get('field_brebo_shift_building')->entity;
    if (!$building instanceof NodeInterface) {
      $form_state->setErrorByName('', $this->t('De dienst heeft geen geldige gebouwlocatie.'));
      return;
    }

    $lat = $this->nullableFloat($form_state->getValue('latitude'));
    $lon = $this->nullableFloat($form_state->getValue('longitude'));
    $accuracy = $this->nullableFloat($form_state->getValue('accuracy'));
    $buildingLat = $this->nullableFloat($building->get('field_brebo_latitude')->value ?? NULL);
    $buildingLon = $this->nullableFloat($building->get('field_brebo_longitude')->value ?? NULL);
    $geo = $this->geoFence->assess($buildingLat, $buildingLon, $lat, $lon, 150.0, $accuracy);
    $note = trim((string) $form_state->getValue('note'));

    if ($geo['status'] !== 'Binnen zone' && $note === '') {
      $form_state->setErrorByName('note', $this->t('Geef een toelichting omdat de locatiecontrole niet binnen de werkzone valt.'));
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $now = new DrupalDateTime('now', 'UTC');
    $event = $storage->create([
      'type' => 'brebo_clock_event',
      'title' => $type . ' · ' . $shift->label() . ' · ' . $now->format('Y-m-d H:i'),
      'field_brebo_clock_shift' => ['target_id' => $shift->id()],
      'field_brebo_clock_building' => ['target_id' => $building->id()],
      'field_brebo_clock_type' => $type,
      'field_brebo_clock_at' => $now->format('Y-m-d\TH:i:s'),
      'field_brebo_clock_lat' => $lat,
      'field_brebo_clock_lon' => $lon,
      'field_brebo_clock_accuracy' => $accuracy,
      'field_brebo_clock_distance' => $geo['distance'],
      'field_brebo_clock_geo_status' => $geo['status'],
      'field_brebo_clock_note' => $note,
    ]);
    if (($contact = $shift->get('field_brebo_shift_contact')->target_id ?? NULL)) {
      $event->set('field_brebo_clock_contact', ['target_id' => $contact]);
    }
    if (($user = $shift->get('field_brebo_shift_user')->target_id ?? NULL)) {
      $event->set('field_brebo_clock_user', ['target_id' => $user]);
    }
    $event->save();

    if ($type === 'Uit') {
      $entry = $this->createOrUpdateConceptHours($shift);
      if ($entry) {
        $this->messenger()->addStatus($this->t('Uitgeklokt. Concepturen zijn automatisch bijgewerkt naar @hours uur.', ['@hours' => $entry->get('field_brebo_time_hours')->value]));
      }
    }
    else {
      $this->messenger()->addStatus($this->t('@action geregistreerd. Locatiecontrole: @status.', ['@action' => $type, '@status' => $geo['status']]));
    }

    $form_state->setRedirect('brebo_office_core.inzet_shift_clock', ['node' => $shift->id()]);
  }

  private function createOrUpdateConceptHours(NodeInterface $shift): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_event')
      ->condition('field_brebo_clock_shift', $shift->id())
      ->sort('field_brebo_clock_at', 'ASC')
      ->execute();
    $events = array_values(array_filter($storage->loadMultiple($ids), static fn ($event): bool => $event instanceof NodeInterface));

    $start = NULL;
    $end = NULL;
    $pauseStart = NULL;
    $pauseSeconds = 0;
    foreach ($events as $event) {
      $type = (string) $event->get('field_brebo_clock_type')->value;
      $at = strtotime((string) $event->get('field_brebo_clock_at')->value . ' UTC');
      if (!$at) {
        continue;
      }
      if ($type === 'In' && $start === NULL) {
        $start = $at;
      }
      elseif ($type === 'Pauze start' && $pauseStart === NULL) {
        $pauseStart = $at;
      }
      elseif ($type === 'Pauze einde' && $pauseStart !== NULL) {
        $pauseSeconds += max(0, $at - $pauseStart);
        $pauseStart = NULL;
      }
      elseif ($type === 'Uit') {
        $end = $at;
      }
    }
    if ($start === NULL || $end === NULL || $end <= $start) {
      return NULL;
    }

    $breakMinutes = (int) round($pauseSeconds / 60);
    $hours = round(max(0, ($end - $start - $pauseSeconds) / 3600), 2);
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_time_entry')
      ->condition('field_brebo_time_shift', $shift->id())
      ->condition('field_brebo_time_status', ['Concept', 'Ingediend'], 'IN')
      ->range(0, 1);
    $existing = $query->execute();
    $entry = $existing ? $storage->load(reset($existing)) : $storage->create(['type' => 'brebo_time_entry']);
    if (!$entry instanceof NodeInterface) {
      return NULL;
    }

    $entry->setTitle('Uren · ' . $shift->label() . ' · ' . gmdate('Y-m-d', $start));
    $entry->set('field_brebo_time_shift', ['target_id' => $shift->id()]);
    $entry->set('field_brebo_time_assignment', ['target_id' => $shift->get('field_brebo_shift_assignment')->target_id]);
    $entry->set('field_brebo_time_budget', ['target_id' => $shift->get('field_brebo_shift_budget')->target_id]);
    if (($contact = $shift->get('field_brebo_shift_contact')->target_id ?? NULL)) {
      $entry->set('field_brebo_time_contact', ['target_id' => $contact]);
    }
    if (($user = $shift->get('field_brebo_shift_user')->target_id ?? NULL)) {
      $entry->set('field_brebo_time_user', ['target_id' => $user]);
    }
    $entry->set('field_brebo_time_start', gmdate('Y-m-d\TH:i:s', $start));
    $entry->set('field_brebo_time_end', gmdate('Y-m-d\TH:i:s', $end));
    $entry->set('field_brebo_time_break_min', $breakMinutes);
    $entry->set('field_brebo_time_hours', $hours);
    $entry->set('field_brebo_time_status', 'Concept');
    $entry->setNewRevision(TRUE);
    $entry->setRevisionLogMessage('Concepturen automatisch herberekend vanuit onwijzigbare klokregistraties.');
    $entry->save();

    return $entry;
  }

  private function lastClockEvent(NodeInterface $shift): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_event')
      ->condition('field_brebo_clock_shift', $shift->id())
      ->sort('field_brebo_clock_at', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $event = $storage->load(reset($ids));
    return $event instanceof NodeInterface ? $event : NULL;
  }

  /**
   * @return string[]
   */
  private function allowedActions(?string $last): array {
    return match ($last) {
      NULL, '', 'Uit' => ['In'],
      'In', 'Pauze einde' => ['Pauze start', 'Uit'],
      'Pauze start' => ['Pauze einde'],
      default => ['In'],
    };
  }

  private function actionKey(string $action): string {
    return strtolower(str_replace([' ', 'ë'], ['_', 'e'], $action));
  }

  private function nullableFloat(mixed $value): ?float {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    return is_numeric($value) ? (float) $value : NULL;
  }

}
