<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formally releases a glass position with an immutable audit record.
 */
final class GlassPositionApproveForm extends ConfirmFormBase {

  private int $positionId;
  private array $position;

  public function __construct(private readonly GlassPositionRepository $repository) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_glass.position_repository'));
  }

  public function getFormId(): string {
    return 'brebo_glass_position_approve_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $position_id = NULL): array {
    $this->positionId = (int) $position_id;
    $this->position = $this->repository->find($this->positionId) ?? [];
    if ($this->position === []) {
      throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    }

    $form['control'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Technische vrijgavecontrole'),
      '#items' => [
        $this->t('Bestelmaat: @width × @height mm', [
          '@width' => $this->position['width_mm'],
          '@height' => $this->position['height_mm'],
        ]),
        $this->t('Glas: @glass', ['@glass' => $this->position['recommended_glass_ref']]),
        $this->t('Windbenutting: @utilization%', [
          '@utilization' => number_format((float) $this->position['wind_utilization'] * 100, 1, ',', '.'),
        ]),
        $this->t('Technische voorcontrole: @state', ['@state' => $this->position['technical_check_state']]),
      ],
    ];
    $form['approval_reference'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vrijgavereferentie'),
      '#description' => $this->t('Bijvoorbeeld een intern controlenummer, berekeningsnummer of documentreferentie.'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['approval_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Vrijgavemotivatie'),
      '#description' => $this->t('Leg vast welke maatvoering, toepassing, productdocumentatie en windberekening zijn gecontroleerd.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    return (string) $this->t('Glaspositie @position technisch vrijgeven?', [
      '@position' => $this->position['position_code'] ?? '#' . $this->positionId,
    ]);
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_glass.position_overview');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->repository->approve(
      $this->positionId,
      (int) $this->currentUser()->id(),
      (string) $form_state->getValue('approval_reference'),
      (string) $form_state->getValue('approval_note'),
    );
    $this->messenger()->addStatus($this->t('Glaspositie is technisch vrijgegeven en de controlesnapshot is vastgelegd.'));
    $form_state->setRedirect('brebo_glass.position_overview');
  }

}
