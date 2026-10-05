<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Brebo\Mail\Service\MailDomainDnsCheckService;
use Brebo\Mail\Service\MailDomainService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CoreMailDomainManagementForm extends FormBase {

  public function __construct(
    private readonly MailDomainService $domains,
    private readonly MailDomainDnsCheckService $dnsCheck,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.core_mail_domain_service'),
      $container->get('brebo_mail_intake.core_mail_domain_dns_check'),
    );
  }

  public function getFormId(): string {
    return 'brebo_core_mail_domain_management_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['header'] = [
      '#markup' => '<div class="brebo-mail-workspace-header"><div><span class="brebo-mail-workspace-header__eyebrow">Mailbeheer</span><h1>Domeinen</h1></div><div class="brebo-mail-workspace-header__state">Brebo\\Mail core</div></div>',
    ];
    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maildomein'),
      '#placeholder' => 'voorbeeld.nl',
    ];
    $form['register'] = [
      '#type' => 'submit',
      '#value' => $this->t('Domein registreren'),
      '#submit' => ['::registerDomain'],
      '#button_type' => 'primary',
    ];

    $form['domains'] = [
      '#type' => 'table',
      '#header' => ['Domein', 'Verificatie', 'MX', 'SPF', 'DKIM', 'DMARC', 'Actie'],
      '#empty' => $this->t('Nog geen maildomeinen geregistreerd.'),
    ];
    foreach ($this->domains->all() as $domain) {
      $id = (int) $domain['id'];
      $record = $this->domains->verificationRecord($id);
      $form['domains'][$id] = [
        'domain' => ['#markup' => '<strong>' . htmlspecialchars((string) $domain['domain'], ENT_QUOTES, 'UTF-8') . '</strong>'],
        'verification' => ['#markup' => '<code>' . htmlspecialchars($record['value'], ENT_QUOTES, 'UTF-8') . '</code>'],
        'mx' => ['#markup' => htmlspecialchars((string) $domain['mx_status'], ENT_QUOTES, 'UTF-8')],
        'spf' => ['#markup' => htmlspecialchars((string) $domain['spf_status'], ENT_QUOTES, 'UTF-8')],
        'dkim' => ['#markup' => htmlspecialchars((string) $domain['dkim_status'], ENT_QUOTES, 'UTF-8')],
        'dmarc' => ['#markup' => htmlspecialchars((string) $domain['dmarc_status'], ENT_QUOTES, 'UTF-8')],
        'check' => [
          '#type' => 'submit',
          '#value' => $this->t('DNS controleren'),
          '#submit' => ['::checkDomain'],
          '#domain_id' => $id,
          '#limit_validation_errors' => [],
        ],
      ];
    }
    return $form;
  }

  public function registerDomain(array &$form, FormStateInterface $form_state): void {
    try {
      $this->domains->register((string) $form_state->getValue('domain'));
      $this->messenger()->addStatus($this->t('Maildomein geregistreerd.'));
      $form_state->setRedirect('brebo_mail_intake.core_mail_domains');
    }
    catch (\Throwable $e) {
      $form_state->setErrorByName('domain', $e->getMessage());
    }
  }

  public function checkDomain(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $result = $this->dnsCheck->check((int) ($trigger['#domain_id'] ?? 0));
    $this->messenger()->addStatus(!empty($result['lookup_error'])
      ? $this->t('DNS tijdelijk niet bereikbaar; laatst bekende status behouden.')
      : $this->t('DNS-status bijgewerkt.'));
    $form_state->setRedirect('brebo_mail_intake.core_mail_domains');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
