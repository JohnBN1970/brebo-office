<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailDomainDnsCheckService;
use Drupal\brebo_mail_intake\Service\MailDomainService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class MailDomainManagementForm extends FormBase {

  public function __construct(
    private readonly MailDomainService $domains,
    private readonly MailDomainDnsCheckService $dnsCheck,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.mail_domain_service'),
      $container->get('brebo_mail_intake.mail_domain_dns_check'),
    );
  }

  public function getFormId(): string {
    return 'brebo_mail_domain_management_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'brebo-mail-domain-workspace';

    $form['header'] = [
      '#markup' => '<div class="brebo-mail-workspace-header"><div><span class="brebo-mail-workspace-header__eyebrow">Mailbeheer</span><h1>Domeinen</h1></div><div class="brebo-mail-workspace-header__state">DNS & routing</div></div>',
    ];

    $form['add'] = [
      '#type' => 'details',
      '#title' => $this->t('Domein toevoegen'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['brebo-mail-domain-add']],
    ];
    $form['add']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maildomein'),
      '#placeholder' => 'voorbeeld.nl',
      '#maxlength' => 253,
    ];
    $form['add']['register'] = [
      '#type' => 'submit',
      '#value' => $this->t('Domein registreren'),
      '#submit' => ['::registerDomain'],
      '#button_type' => 'primary',
    ];

    $form['domains'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Domein'),
        $this->t('Verificatie'),
        $this->t('MX'),
        $this->t('SPF'),
        $this->t('DKIM'),
        $this->t('DMARC'),
        $this->t('Actie'),
      ],
      '#empty' => $this->t('Nog geen maildomeinen geregistreerd.'),
      '#attributes' => ['class' => ['brebo-mail-domain-table']],
    ];

    foreach ($this->domains->all() as $domain) {
      $id = (int) $domain['id'];
      $verification = $this->domains->verificationRecord($id);
      $status = static fn(string $value): string => match ($value) {
        'ok', 'verified' => '✓ ' . $value,
        'missing' => '— ontbreekt',
        'pending' => '… controle nodig',
        default => '? ' . ($value ?: 'unknown'),
      };

      $form['domains'][$id]['domain'] = [
        '#markup' => '<strong>' . htmlspecialchars((string) $domain['domain'], ENT_QUOTES, 'UTF-8') . '</strong><br><small>' . htmlspecialchars((string) $domain['status'], ENT_QUOTES, 'UTF-8') . '</small>',
      ];
      $form['domains'][$id]['verification'] = [
        '#markup' => '<code>TXT @</code><br><code>' . htmlspecialchars((string) $verification['value'], ENT_QUOTES, 'UTF-8') . '</code>',
      ];
      $form['domains'][$id]['mx'] = ['#markup' => $status((string) $domain['mx_status'])];
      $form['domains'][$id]['spf'] = ['#markup' => $status((string) $domain['spf_status'])];
      $form['domains'][$id]['dkim'] = ['#markup' => $status((string) $domain['dkim_status'])];
      $form['domains'][$id]['dmarc'] = ['#markup' => $status((string) $domain['dmarc_status'])];
      $form['domains'][$id]['action'] = [
        '#type' => 'submit',
        '#value' => $this->t('DNS controleren'),
        '#name' => 'check_domain_' . $id,
        '#submit' => ['::checkDomain'],
        '#domain_id' => $id,
        '#limit_validation_errors' => [],
      ];
    }

    return $form;
  }

  public function registerDomain(array &$form, FormStateInterface $form_state): void {
    try {
      $this->domains->register((string) $form_state->getValue('domain'));
      $this->messenger()->addStatus($this->t('Maildomein geregistreerd. Plaats eerst het verificatie-TXT-record en voer daarna DNS-controle uit.'));
      $form_state->setRedirect('brebo_mail_intake.mail_domains');
    }
    catch (\InvalidArgumentException $e) {
      $form_state->setErrorByName('domain', $e->getMessage());
    }
  }

  public function checkDomain(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $domainId = (int) ($trigger['#domain_id'] ?? 0);
    if ($domainId <= 0) {
      $this->messenger()->addError($this->t('Geen geldig maildomein geselecteerd.'));
      return;
    }

    $result = $this->dnsCheck->check($domainId);
    $this->messenger()->addStatus($result['verified']
      ? $this->t('DNS gecontroleerd: domeineigendom is geverifieerd.')
      : $this->t('DNS gecontroleerd: verificatie-TXT is nog niet gevonden.'));
    $form_state->setRedirect('brebo_mail_intake.mail_domains');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
