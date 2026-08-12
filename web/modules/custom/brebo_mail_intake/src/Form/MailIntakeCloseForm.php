<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Confirms that a communication has been human-reviewed and handled.
 */
final class MailIntakeCloseForm extends ConfirmFormBase {

  private ?NodeInterface $communication = NULL;

  public function getFormId(): string {
    return 'brebo_mail_intake_close_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication' || !$node->hasField('field_brebo_intake_status')) {
      throw new \InvalidArgumentException('Alleen BREBO Communication kan via Mail Intake worden afgehandeld.');
    }

    $this->communication = $node;
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    return $this->t('Deze communicatie als afgehandeld markeren?');
  }

  public function getDescription(): string {
    return $this->t('De communicatie blijft als bron en dossierhistorie bewaard, maar verdwijnt uit de actieve Mail Intake-werkbak. Eventuele aparte vervolgacties blijven zelfstandig bestaan.');
  }

  public function getConfirmText(): string {
    return $this->t('Afhandelen');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_mail_intake.review_queue');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->communication instanceof NodeInterface) {
      throw new \RuntimeException('Geen communicatie beschikbaar om af te handelen.');
    }

    $this->communication->set('field_brebo_intake_status', 'Afgehandeld');
    $this->communication->setNewRevision(TRUE);
    $this->communication->setRevisionLogMessage('Mail Intake handmatig afgehandeld; bron blijft bewaard.');
    $this->communication->save();

    $this->messenger()->addStatus($this->t('Communicatie is afgehandeld en uit de actieve werkbak verwijderd.'));
    $form_state->setRedirect('brebo_mail_intake.review_queue');
  }

}
