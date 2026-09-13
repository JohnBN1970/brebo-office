<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Form;

use Drupal\brebo_building_data\Service\BuildingTruthRepository;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Controlled verifier for one pending building-truth proposal. */
final class BuildingTruthProposalReviewForm extends FormBase {

  private ?array $proposal = NULL;
  private ?NodeInterface $building = NULL;

  public function __construct(private readonly BuildingTruthRepository $truth) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_building_data.truth_repository'));
  }

  public function getFormId(): string {
    return 'brebo_building_truth_proposal_review_form';
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Intentionally empty: the accept/reject buttons use dedicated handlers.
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $proposal = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update', $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }

    $proposalRow = NULL;
    foreach ($this->truth->pendingProposals((int) $node->id()) as $candidate) {
      if ((int) ($candidate['id'] ?? 0) === (int) $proposal) {
        $proposalRow = $candidate;
        break;
      }
    }
    if ($proposalRow === NULL) {
      throw new NotFoundHttpException('Revisievoorstel niet gevonden of niet meer openstaand.');
    }

    $this->building = $node;
    $this->proposal = $proposalRow;

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      'text' => ['#markup' => '<strong>Verificatiegrens</strong><br>Accepteren maakt dit voorstel onderdeel van de actuele gebouwwaarheid. Een bestaande waarde wordt daarbij automatisch naar de gebouwhistorie geschreven.'],
    ];
    $form['proposal'] = [
      '#type' => 'table',
      '#header' => [$this->t('Onderdeel'), $this->t('Waarde')],
      '#rows' => [
        [$this->t('Feit'), (string) $proposalRow['fact_key']],
        [$this->t('Voorgestelde waarde'), $this->renderValue($proposalRow['value'] ?? NULL)],
        [$this->t('Bron'), trim((string) ($proposalRow['source_type'] ?? '')) ?: '—'],
        [$this->t('Bronproject'), !empty($proposalRow['source_project_nid']) ? '#' . (int) $proposalRow['source_project_nid'] : '—'],
        [$this->t('Bronreferentie'), trim((string) ($proposalRow['source_ref'] ?? '')) ?: '—'],
        [$this->t('Reden'), trim((string) ($proposalRow['reason'] ?? '')) ?: '—'],
        [$this->t('Bewijs'), $this->renderValue($proposalRow['evidence'] ?? [])],
      ],
    ];
    $form['verification_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Verificatienotitie'),
      '#required' => TRUE,
      '#rows' => 4,
      '#description' => $this->t('Leg kort vast wat is gecontroleerd en waarom dit voorstel wordt geaccepteerd of afgewezen.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['accept'] = [
      '#type' => 'submit',
      '#value' => $this->t('Accepteren als gebouwwaarheid'),
      '#button_type' => 'primary',
      '#submit' => ['::accept'],
    ];
    $form['actions']['reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Afwijzen'),
      '#submit' => ['::reject'],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => Url::fromRoute('brebo_building_data.truth_workbench', ['node' => $node->id()]),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Intentionally empty: submit handling is delegated to the explicit
    // accept/reject button handlers above. FormBase still requires this
    // method because it implements FormInterface.
  }

  public function accept(array &$form, FormStateInterface $form_state): void {
    $proposalId = (int) ($this->proposal['id'] ?? 0);
    $note = trim((string) $form_state->getValue('verification_note'));
    $this->truth->acceptProposal($proposalId, (int) $this->currentUser()->id(), $note);
    $this->messenger()->addStatus($this->t('Revisievoorstel is geaccepteerd en vormt nu de actuele gebouwwaarheid.'));
    $this->redirectToWorkbench($form_state);
  }

  public function reject(array &$form, FormStateInterface $form_state): void {
    $proposalId = (int) ($this->proposal['id'] ?? 0);
    $note = trim((string) $form_state->getValue('verification_note'));
    $this->truth->rejectProposal($proposalId, (int) $this->currentUser()->id(), $note);
    $this->messenger()->addStatus($this->t('Revisievoorstel is afgewezen; de actuele gebouwwaarheid is niet gewijzigd.'));
    $this->redirectToWorkbench($form_state);
  }

  private function redirectToWorkbench(FormStateInterface $form_state): void {
    $form_state->setRedirect('brebo_building_data.truth_workbench', ['node' => (int) $this->building?->id()]);
  }

  private function renderValue(mixed $value): string {
    if (is_bool($value)) return $value ? 'Ja' : 'Nee';
    if (is_scalar($value) || $value === NULL) return $value === NULL ? '—' : (string) $value;
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
  }

}
