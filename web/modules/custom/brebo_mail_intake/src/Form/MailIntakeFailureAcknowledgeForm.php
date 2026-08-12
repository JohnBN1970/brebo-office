<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailIntakeFailureRegistry;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms acknowledgement of one technical Mail Intake exception.
 */
final class MailIntakeFailureAcknowledgeForm extends ConfirmFormBase {

  private string $sourceReference = '';

  public function __construct(
    private readonly MailIntakeFailureRegistry $registry,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_mail_intake.failure_registry'));
  }

  public function getFormId(): string {
    return 'brebo_mail_intake_failure_acknowledge';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $source_ref = NULL): array {
    $this->sourceReference = trim((string) $source_ref);
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    return 'Technische Mail Intake-uitzondering als gezien markeren?';
  }

  public function getDescription(): string {
    return 'Dit verwijdert of wijzigt de bronmail niet. Alleen de technische uitzondering verdwijnt uit de actieve uitzonderingenlijst.';
  }

  public function getConfirmText(): string {
    return 'Markeer als gezien';
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_mail_intake.review_queue');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->registry->acknowledge($this->sourceReference)) {
      $this->messenger()->addStatus('Technische Mail Intake-uitzondering is als gezien gemarkeerd.');
    }
    else {
      $this->messenger()->addWarning('De technische uitzondering bestaat niet meer of was al afgehandeld.');
    }
    $form_state->setRedirect('brebo_mail_intake.review_queue');
  }

}
