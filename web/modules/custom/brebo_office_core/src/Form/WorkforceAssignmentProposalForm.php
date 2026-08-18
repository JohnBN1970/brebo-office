<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\WorkforcePlanningOptimizer;
use Drupal\brebo_office_core\Service\WorkforceQualificationMatcher;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds and applies explainable personnel proposals for one shift.
 */
final class WorkforceAssignmentProposalForm extends FormBase {

  private array $ranked = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforcePlanningOptimizer $optimizer,
    private readonly WorkforceQualificationMatcher $qualificationMatcher,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.workforce_planning_optimizer'),
      $container->get('brebo_office_core.workforce_qualification_matcher'),
    );
  }

  public function getFormId(): string {
    return 'brebo_workforce_assignment_proposal';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_shift') {
      throw new NotFoundHttpException();
    }
    $this->ranked = $this->buildCandidates($node);
    $form_state->set('shift_id', (int) $node->id());
    $eligible = [];
    $rows = [];
    foreach ($this->ranked as $candidate) {
      if ($candidate['eligible']) {
        $eligible[(string) $candidate['id']] = (string) $candidate['label'];
      }
      $rows[] = [
        $candidate['label'],
        $candidate['eligible'] ? $this->t('Geschikt') : $this->t('Afgevallen'),
        number_format((float) $candidate['score'], 2, ',', '.'),
        $candidate['explanation'],
      ];
    }

    $start = new \DateTimeImmutable((string) $node->get('field_brebo_shift_start')->value);
    $end = new \DateTimeImmutable((string) $node->get('field_brebo_shift_end')->value);
    $form['context'] = [
      '#type' => 'item',
      '#title' => $this->t('Dienst'),
      '#markup' => $this->t('@shift — @start tot @end — benodigde bezetting: @people', [
        '@shift' => $node->label(),
        '@start' => $start->format('d-m-Y H:i'),
        '@end' => $end->format('d-m-Y H:i'),
        '@people' => (int) ($node->get('field_brebo_shift_people')->value ?? 1),
      ]),
    ];
    $form['principle'] = [
      '#markup' => '<p>' . $this->t('Alleen personen met bevestigde beschikbaarheid, voldoende uren, geldige kwalificaties en zonder roosterconflict kunnen worden gekozen. De score bepaalt uitsluitend de volgorde van geschikte kandidaten.') . '</p>',
    ];
    $form['candidates'] = [
      '#type' => 'table',
      '#header' => [$this->t('Kandidaat'), $this->t('Besluit'), $this->t('Score'), $this->t('Uitleg')],
      '#rows' => $rows,
      '#empty' => $this->t('Geen contactpersonen gevonden.'),
      '#sticky' => TRUE,
    ];
    $form['candidate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Toe te passen kandidaat'),
      '#options' => $eligible,
      '#required' => TRUE,
      '#empty' => $this->t('Geen geschikte kandidaten beschikbaar.'),
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik heb het voorstel en de motivatie gecontroleerd en pas deze personeelskeuze bewust toe.'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Kandidaat toepassen'),
      '#button_type' => 'primary',
      '#disabled' => $eligible === [],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => Url::fromRoute('brebo_office_core.inzet_schedule'),
      '#attributes' => ['class' => ['button']],
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $selected = (int) $form_state->getValue('candidate');
    $candidate = $this->findCandidate($selected);
    if ($candidate === NULL || !$candidate['eligible']) {
      $form_state->setErrorByName('candidate', $this->t('Deze kandidaat is niet meer als geschikt voorstel beschikbaar.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $shift = $storage->load((int) $form_state->get('shift_id'));
    $candidate = $this->findCandidate((int) $form_state->getValue('candidate'));
    if (!$shift instanceof NodeInterface || $shift->bundle() !== 'brebo_shift' || $candidate === NULL || !$candidate['eligible']) {
      throw new \RuntimeException('Het personeelsvoorstel kon niet veilig worden toegepast.');
    }

    $shift->setNewRevision(TRUE);
    $shift->setRevisionLogMessage('Personeelsvoorstel bewust toegepast via BREBO Inzet.');
    $shift->set('field_brebo_shift_contact', (int) $candidate['id']);
    $shift->set('field_brebo_shift_user', NULL);
    $shift->set('field_brebo_shift_team', NULL);
    $shift->set('field_brebo_shift_open', (int) ($shift->get('field_brebo_shift_people')->value ?? 1) > 1);
    if ($shift->hasField('field_brebo_shift_proposal_score')) {
      $shift->set('field_brebo_shift_proposal_score', number_format((float) $candidate['score'], 2, '.', ''));
      $shift->set('field_brebo_shift_proposal_note', (string) $candidate['explanation']);
      $shift->set('field_brebo_shift_assigned_by', (int) $this->currentUser()->id());
      $shift->set('field_brebo_shift_assigned_at', gmdate('Y-m-d\TH:i:s', \Drupal::time()->getRequestTime()));
    }
    $shift->save();

    $this->messenger()->addStatus($this->t('@person is toegepast op @shift. De dienst blijft open wanneer aanvullende bezetting nodig is.', [
      '@person' => $candidate['label'],
      '@shift' => $shift->label(),
    ]));
    $form_state->setRedirect('brebo_office_core.inzet_schedule', [], [
      'query' => ['week' => substr((string) $shift->get('field_brebo_shift_start')->value, 0, 10)],
    ]);
  }

  private function buildCandidates(NodeInterface $shift): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $start = new \DateTimeImmutable((string) $shift->get('field_brebo_shift_start')->value);
    $end = new \DateTimeImmutable((string) $shift->get('field_brebo_shift_end')->value);
    $requiredHours = max(0.0, (($end->getTimestamp() - $start->getTimestamp()) / 3600)
      - ((int) ($shift->get('field_brebo_shift_break_min')->value ?? 0) / 60));
    $skillRequirements = [];
    foreach ($shift->get('field_brebo_shift_skills')->referencedEntities() as $skill) {
      if ($skill instanceof NodeInterface) {
        $skillRequirements[(string) $skill->id()] = (string) $skill->label();
      }
    }

    $contactIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact')
      ->sort('title', 'ASC')
      ->range(0, 250)
      ->execute();
    $availability = $this->availabilityByContact($start, $end);
    $qualifications = $this->qualificationsByContact(array_keys($contactIds));
    $overlap = $this->shiftFactsByContact($shift, $start, $end);

    $candidates = [];
    foreach ($storage->loadMultiple($contactIds) as $contact) {
      if (!$contact instanceof NodeInterface) {
        continue;
      }
      $id = (int) $contact->id();
      $availableHours = (float) ($availability[$id]['hours'] ?? 0);
      $qualification = $this->qualificationMatcher->match(
        $skillRequirements,
        $qualifications[$id] ?? [],
        new \DateTimeImmutable($start->format('Y-m-d')),
      );
      $candidates[] = [
        'id' => $id,
        'label' => (string) $contact->label(),
        'available' => (bool) ($availability[$id]['available'] ?? FALSE),
        'qualification_status' => $qualification['status'],
        'overlap' => (bool) ($overlap[$id]['overlap'] ?? FALSE),
        'remaining_hours' => max(0.0, $availableHours - (float) ($overlap[$id]['hours'] ?? 0)),
        'distance_km' => NULL,
        'quality_score' => 50,
        'continuity' => (bool) ($overlap[$id]['continuity'] ?? FALSE),
      ];
    }
    return $this->optimizer->rank($candidates, $requiredHours);
  }

  private function availabilityByContact(\DateTimeImmutable $start, \DateTimeImmutable $end): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_availability')
      ->condition('field_brebo_avail_status', 'Bevestigd')
      ->condition('field_brebo_avail_start', $end->format('Y-m-d'), '<=')
      ->condition('field_brebo_avail_end', $start->format('Y-m-d'), '>=')
      ->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $period) {
      if (!$period instanceof NodeInterface || !($contact = $period->get('field_brebo_avail_contact')->entity)) {
        continue;
      }
      $id = (int) $contact->id();
      $blocked = in_array((string) $period->get('field_brebo_avail_type')->value, ['Verlof', 'Niet beschikbaar'], TRUE);
      $result[$id]['available'] = !$blocked && ($result[$id]['available'] ?? TRUE);
      $result[$id]['hours'] = max((float) ($result[$id]['hours'] ?? 0), (float) ($period->get('field_brebo_avail_hours_day')->value ?? 0));
    }
    return $result;
  }

  private function qualificationsByContact(array $contactIds): array {
    if ($contactIds === []) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_qualification')
      ->condition('field_brebo_qual_contact.target_id', $contactIds, 'IN')
      ->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $qualification) {
      if (!$qualification instanceof NodeInterface || !($contact = $qualification->get('field_brebo_qual_contact')->entity)) {
        continue;
      }
      $skill = $qualification->get('field_brebo_qual_skill')->entity;
      $result[(int) $contact->id()][] = [
        'skill_id' => $skill instanceof NodeInterface ? (string) $skill->id() : '',
        'label' => $skill instanceof NodeInterface ? (string) $skill->label() : '',
        'status' => (string) ($qualification->get('field_brebo_qual_status')->value ?? ''),
        'expires' => (string) ($qualification->get('field_brebo_qual_expires')->value ?? ''),
      ];
    }
    return $result;
  }

  private function shiftFactsByContact(NodeInterface $current, \DateTimeImmutable $start, \DateTimeImmutable $end): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_shift')
      ->condition('nid', $current->id(), '<>')
      ->condition('field_brebo_shift_status', 'Vervallen', '<>')
      ->condition('field_brebo_shift_start', $end->format('Y-m-d\TH:i:s'), '<')
      ->condition('field_brebo_shift_end', $start->format('Y-m-d\TH:i:s'), '>')
      ->exists('field_brebo_shift_contact')
      ->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $other) {
      if (!$other instanceof NodeInterface || !($contact = $other->get('field_brebo_shift_contact')->entity)) {
        continue;
      }
      $otherStart = new \DateTimeImmutable((string) $other->get('field_brebo_shift_start')->value);
      $otherEnd = new \DateTimeImmutable((string) $other->get('field_brebo_shift_end')->value);
      $id = (int) $contact->id();
      $result[$id]['overlap'] = TRUE;
      $result[$id]['hours'] = (float) ($result[$id]['hours'] ?? 0)
        + max(0.0, ($otherEnd->getTimestamp() - $otherStart->getTimestamp()) / 3600);
      $result[$id]['continuity'] = $other->get('field_brebo_shift_project')->target_id === $current->get('field_brebo_shift_project')->target_id;
    }
    return $result;
  }

  private function findCandidate(int $id): ?array {
    foreach ($this->ranked as $candidate) {
      if ((int) $candidate['id'] === $id) {
        return $candidate;
      }
    }
    return NULL;
  }

}
