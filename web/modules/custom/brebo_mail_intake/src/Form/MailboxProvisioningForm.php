<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailDomainService;
use Drupal\brebo_mail_intake\Service\MailboxProvisioningService;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class MailboxProvisioningForm extends FormBase {

  public function __construct(
    private readonly MailDomainService $domains,
    private readonly MailboxRepository $mailboxes,
    private readonly MailboxProvisioningService $provisioning,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.mail_domain_service'),
      $container->get('brebo_mail_intake.mailbox_repository'),
      $container->get('brebo_mail_intake.mailbox_provisioning'),
    );
  }

  public function getFormId(): string {
    return 'brebo_mailbox_provisioning_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'brebo-mail-provisioning-workspace';
    $form['header'] = [
      '#markup' => '<div class="brebo-mail-workspace-header"><div><span class="brebo-mail-workspace-header__eyebrow">Mailbeheer</span><h1>Mailboxen & aliassen</h1></div><div class="brebo-mail-workspace-header__state">Provisioning</div></div>',
    ];

    $verifiedDomains = [];
    foreach ($this->domains->all() as $domain) {
      if ((string) ($domain['status'] ?? '') === 'verified') {
        $verifiedDomains[(int) $domain['id']] = (string) $domain['domain'];
      }
    }

    $form['mailbox'] = [
      '#type' => 'details',
      '#title' => $this->t('Mailbox aanmaken'),
      '#open' => TRUE,
    ];
    $form['mailbox']['local_part'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Adres'),
      '#placeholder' => 'calculatie',
      '#required' => TRUE,
    ];
    $form['mailbox']['domain_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Domein'),
      '#options' => $verifiedDomains,
      '#required' => TRUE,
      '#empty_option' => $verifiedDomains === [] ? $this->t('Geen geverifieerde domeinen') : NULL,
      '#disabled' => $verifiedDomains === [],
    ];
    $form['mailbox']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Naam'),
      '#placeholder' => 'Calculatie',
      '#required' => TRUE,
    ];
    $form['mailbox']['privacy_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'functional' => $this->t('Functioneel'),
        'personal' => $this->t('Persoonlijk'),
      ],
      '#default_value' => 'functional',
    ];
    $form['mailbox']['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Mailbox aanmaken'),
      '#submit' => ['::createMailbox'],
      '#button_type' => 'primary',
      '#disabled' => $verifiedDomains === [],
    ];

    $mailboxOptions = [];
    foreach ($this->mailboxes->all() as $mailbox) {
      if (!empty($mailbox['active'])) {
        $mailboxOptions[(int) $mailbox['id']] = (string) $mailbox['label'] . ' — ' . (string) $mailbox['address'];
      }
    }

    $form['alias'] = [
      '#type' => 'details',
      '#title' => $this->t('Alias toevoegen'),
      '#open' => TRUE,
    ];
    $form['alias']['mailbox_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Doelmailbox'),
      '#options' => $mailboxOptions,
      '#required' => TRUE,
      '#disabled' => $mailboxOptions === [],
    ];
    $form['alias']['alias_local_part'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Aliasadres'),
      '#placeholder' => 'offertes',
      '#required' => TRUE,
    ];
    $form['alias']['alias_domain_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Domein'),
      '#options' => $verifiedDomains,
      '#required' => TRUE,
      '#disabled' => $verifiedDomains === [],
    ];
    $form['alias']['add_alias'] = [
      '#type' => 'submit',
      '#value' => $this->t('Alias toevoegen'),
      '#submit' => ['::addAlias'],
      '#disabled' => $verifiedDomains === [] || $mailboxOptions === [],
    ];

    $form['existing'] = [
      '#type' => 'table',
      '#header' => [$this->t('Mailbox'), $this->t('Adres'), $this->t('Type'), $this->t('Aliassen')],
      '#empty' => $this->t('Nog geen mailboxen beschikbaar.'),
    ];
    foreach ($this->mailboxes->all() as $mailbox) {
      $aliases = $this->provisioning->aliases((int) $mailbox['id']);
      $aliasLabels = array_map(static fn(array $alias): string => (string) $alias['address'], $aliases);
      $form['existing'][(int) $mailbox['id']] = [
        'label' => ['#markup' => '<strong>' . htmlspecialchars((string) $mailbox['label'], ENT_QUOTES, 'UTF-8') . '</strong>'],
        'address' => ['#markup' => htmlspecialchars((string) $mailbox['address'], ENT_QUOTES, 'UTF-8')],
        'type' => ['#markup' => htmlspecialchars((string) $mailbox['privacy_type'], ENT_QUOTES, 'UTF-8')],
        'aliases' => ['#markup' => $aliasLabels === [] ? '—' : htmlspecialchars(implode(', ', $aliasLabels), ENT_QUOTES, 'UTF-8')],
      ];
    }

    return $form;
  }

  public function createMailbox(array &$form, FormStateInterface $form_state): void {
    try {
      $this->provisioning->createMailbox(
        (string) $form_state->getValue('local_part'),
        (int) $form_state->getValue('domain_id'),
        (string) $form_state->getValue('label'),
        (string) $form_state->getValue('privacy_type'),
      );
      $this->messenger()->addStatus($this->t('Mailbox in BREBO Office aangemaakt.'));
      $form_state->setRedirect('brebo_mail_intake.mailbox_provisioning');
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  public function addAlias(array &$form, FormStateInterface $form_state): void {
    try {
      $this->provisioning->addAlias(
        (int) $form_state->getValue('mailbox_id'),
        (string) $form_state->getValue('alias_local_part'),
        (int) $form_state->getValue('alias_domain_id'),
      );
      $this->messenger()->addStatus($this->t('Alias toegevoegd.'));
      $form_state->setRedirect('brebo_mail_intake.mailbox_provisioning');
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
