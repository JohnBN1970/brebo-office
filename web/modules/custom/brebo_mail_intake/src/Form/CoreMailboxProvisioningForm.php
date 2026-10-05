<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Brebo\Mail\Service\MailDomainService;
use Brebo\Mail\Service\MailboxProvisioningService;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CoreMailboxProvisioningForm extends FormBase {

  public function __construct(
    private readonly MailDomainService $domains,
    private readonly MailboxProvisioningService $provisioning,
    private readonly MailboxRepository $mailboxes,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.core_mail_domain_service'),
      $container->get('brebo_mail_intake.core_mailbox_provisioning'),
      $container->get('brebo_mail_intake.mailbox_repository'),
    );
  }

  public function getFormId(): string {
    return 'brebo_core_mailbox_provisioning_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $verified = [];
    foreach ($this->domains->all() as $domain) {
      if ((string) ($domain['status'] ?? '') === 'verified') {
        $verified[(int) $domain['id']] = (string) $domain['domain'];
      }
    }

    $form['header'] = [
      '#markup' => '<div class="brebo-mail-workspace-header"><div><span class="brebo-mail-workspace-header__eyebrow">Mailbeheer</span><h1>Mailboxen & aliassen</h1></div><div class="brebo-mail-workspace-header__state">Brebo\\Mail core</div></div>',
    ];

    $form['mailbox'] = ['#type' => 'details', '#title' => $this->t('Mailbox aanmaken'), '#open' => TRUE];
    $form['mailbox']['local'] = ['#type' => 'textfield', '#title' => $this->t('Adres'), '#required' => TRUE];
    $form['mailbox']['domain'] = ['#type' => 'select', '#title' => $this->t('Domein'), '#options' => $verified, '#required' => TRUE];
    $form['mailbox']['label'] = ['#type' => 'textfield', '#title' => $this->t('Naam'), '#required' => TRUE];
    $form['mailbox']['type'] = ['#type' => 'select', '#title' => $this->t('Type'), '#options' => ['functional' => 'Functioneel', 'personal' => 'Persoonlijk']];
    $form['mailbox']['create'] = ['#type' => 'submit', '#value' => $this->t('Mailbox aanmaken'), '#submit' => ['::createMailbox'], '#disabled' => $verified === []];

    $mailboxOptions = [];
    foreach ($this->mailboxes->all() as $mailbox) {
      if (!empty($mailbox['active'])) {
        $mailboxOptions[(int) $mailbox['id']] = (string) $mailbox['label'] . ' — ' . (string) $mailbox['address'];
      }
    }

    $form['alias'] = ['#type' => 'details', '#title' => $this->t('Alias toevoegen'), '#open' => TRUE];
    $form['alias']['mailbox'] = ['#type' => 'select', '#title' => $this->t('Doelmailbox'), '#options' => $mailboxOptions, '#required' => TRUE];
    $form['alias']['local'] = ['#type' => 'textfield', '#title' => $this->t('Aliasadres'), '#required' => TRUE];
    $form['alias']['domain'] = ['#type' => 'select', '#title' => $this->t('Domein'), '#options' => $verified, '#required' => TRUE];
    $form['alias']['create'] = ['#type' => 'submit', '#value' => $this->t('Alias toevoegen'), '#submit' => ['::createAlias'], '#disabled' => $verified === [] || $mailboxOptions === []];

    return $form;
  }

  public function createMailbox(array &$form, FormStateInterface $form_state): void {
    try {
      $this->provisioning->createMailbox(
        (string) $form_state->getValue(['mailbox', 'local']),
        (int) $form_state->getValue(['mailbox', 'domain']),
        (string) $form_state->getValue(['mailbox', 'label']),
        (string) $form_state->getValue(['mailbox', 'type']),
      );
      $this->messenger()->addStatus($this->t('Mailbox aangemaakt.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
    $form_state->setRedirect('brebo_mail_intake.core_mailboxes');
  }

  public function createAlias(array &$form, FormStateInterface $form_state): void {
    try {
      $this->provisioning->addAlias(
        (int) $form_state->getValue(['alias', 'mailbox']),
        (string) $form_state->getValue(['alias', 'local']),
        (int) $form_state->getValue(['alias', 'domain']),
      );
      $this->messenger()->addStatus($this->t('Alias toegevoegd.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
    $form_state->setRedirect('brebo_mail_intake.core_mailboxes');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
