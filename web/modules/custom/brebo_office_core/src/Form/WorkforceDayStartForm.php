<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a versioned digital day start from authoritative shift data.
 */
final class WorkforceDayStartForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'brebo_workforce_day_start';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_shift') {
      throw new NotFoundHttpException();
    }
    $project = $node->get('field_brebo_shift_project')->entity;
    $building = $node->get('field_brebo_shift_building')->entity;
    $activity = $node->get('field_brebo_shift_activity')->entity;
    $start = new \DateTimeImmutable((string) $node->get('field_brebo_shift_start')->value);
    $end = new \DateTimeImmutable((string) $node->get('field_brebo_shift_end')->value);
    $version = $this->nextVersion($node);
    $form_state->set('shift_id', (int) $node->id());
    $form_state->set('version', $version);

    $bookings = $this->bookings($node);
    $resourceLabels = [];
    foreach ($bookings as $booking) {
      $resource = $booking->get('field_brebo_booking_resource')->entity;
      $resourceLabels[] = ($resource instanceof NodeInterface ? $resource->label() : $booking->label())
        . ' (' . ($booking->get('field_brebo_booking_control')->value ?? 'niet gecontroleerd') . ')';
    }

    $form['source'] = [
      '#type' => 'details',
      '#title' => $this->t('Automatisch overgenomen broninformatie'),
      '#open' => TRUE,
    ];
    $form['source']['table'] = [
      '#type' => 'table',
      '#rows' => [
        [$this->t('Versie'), $version],
        [$this->t('Dienst'), $node->label()],
        [$this->t('Project'), $project instanceof NodeInterface ? $project->label() : '—'],
        [$this->t('Gebouw'), $building instanceof NodeInterface ? $building->label() : '—'],
        [$this->t('Tijd'), $start->format('d-m-Y H:i') . ' – ' . $end->format('d-m-Y H:i')],
        [$this->t('Activiteit'), $activity instanceof NodeInterface ? $activity->label() : '—'],
        [$this->t('Materieel'), $resourceLabels === [] ? $this->t('Geen reserveringen gekoppeld') : implode(', ', $resourceLabels)],
        [$this->t('Kwalificatiecontrole'), (string) ($node->get('field_brebo_shift_match')->value ?? 'Niet gecontroleerd')],
      ],
    ];

    $instruction = trim((string) ($node->get('field_brebo_shift_notes')->value ?? ''));
    $workDefault = ($activity instanceof NodeInterface ? $activity->label() : $node->label())
      . ($instruction !== '' ? "\n\nWerkinstructie dienst:\n" . $instruction : '');
    $form['work'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Werkzaamheden en werkvolgorde'),
      '#default_value' => $workDefault,
      '#required' => TRUE,
      '#rows' => 6,
    ];
    $form['access'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Toegang en logistiek'),
      '#description' => $this->t('Leg sleutels, parkeren, bewonersafspraken, opslag en aanvoerroute concreet vast.'),
      '#required' => TRUE,
      '#rows' => 5,
    ];
    $form['risks'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Risico’s en beheersmaatregelen'),
      '#description' => $this->t('Benoem per risico de oorzaak, mogelijke consequentie en beheersmaatregel.'),
      '#required' => TRUE,
      '#rows' => 6,
    ];
    $form['controls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Kwaliteits- en controlepunten'),
      '#description' => $this->t('Benoem hold points, fotobewijs, maatvoering, producteisen en wie mag vrijgeven.'),
      '#required' => TRUE,
      '#rows' => 6,
    ];
    $form['contacts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Contact- en escalatiegegevens'),
      '#description' => $this->t('Vermeld uitvoerder, projectleider, bewonerscontact en escalatieroute.'),
      '#required' => TRUE,
      '#rows' => 4,
    ];
    $form['acknowledge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik bevestig dat niet-afleidbare informatie is gecontroleerd en dat deze dagstart eerst als concept wordt opgeslagen.'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Conceptdagstart maken'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $shift = $storage->load((int) $form_state->get('shift_id'));
    if (!$shift instanceof NodeInterface || $shift->bundle() !== 'brebo_shift') {
      throw new \RuntimeException('De gekoppelde dienst is niet meer beschikbaar.');
    }
    $bookingIds = array_map(
      static fn (NodeInterface $booking): int => (int) $booking->id(),
      $this->bookings($shift),
    );
    $dayStart = $storage->create([
      'type' => 'brebo_day_start',
      'title' => 'Dagstart',
      'field_brebo_day_shift' => $shift->id(),
      'field_brebo_day_project' => $shift->get('field_brebo_shift_project')->target_id,
      'field_brebo_day_building' => $shift->get('field_brebo_shift_building')->target_id,
      'field_brebo_day_version' => (int) $form_state->get('version'),
      'field_brebo_day_status' => 'Concept',
      'field_brebo_day_generated_by' => (int) $this->currentUser()->id(),
      'field_brebo_day_generated_at' => gmdate('Y-m-d\TH:i:s', \Drupal::time()->getRequestTime()),
      'field_brebo_day_work' => (string) $form_state->getValue('work'),
      'field_brebo_day_access' => (string) $form_state->getValue('access'),
      'field_brebo_day_risks' => (string) $form_state->getValue('risks'),
      'field_brebo_day_controls' => (string) $form_state->getValue('controls'),
      'field_brebo_day_contacts' => (string) $form_state->getValue('contacts'),
      'field_brebo_day_bookings' => $bookingIds,
    ]);
    $dayStart->save();
    $this->messenger()->addStatus($this->t('Digitale dagstart versie @version is als concept aangemaakt en automatisch op gereedheid gecontroleerd.', [
      '@version' => $form_state->get('version'),
    ]));
    $form_state->setRedirect('entity.node.canonical', ['node' => $dayStart->id()]);
  }

  /**
   * @return array<int, NodeInterface>
   */
  private function bookings(NodeInterface $shift): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_resource_booking')
      ->condition('field_brebo_booking_shift.target_id', $shift->id())
      ->condition('field_brebo_booking_status', ['Geannuleerd', 'Retour'], 'NOT IN')
      ->sort('field_brebo_booking_start', 'ASC')
      ->execute();
    return array_values(array_filter(
      $storage->loadMultiple($ids),
      static fn ($entity): bool => $entity instanceof NodeInterface,
    ));
  }

  private function nextVersion(NodeInterface $shift): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_day_start')
      ->condition('field_brebo_day_shift.target_id', $shift->id())
      ->sort('field_brebo_day_version', 'DESC')
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return 1;
    }
    $latest = $storage->load(reset($ids));
    return $latest instanceof NodeInterface
      ? (int) $latest->get('field_brebo_day_version')->value + 1
      : 1;
  }

}
