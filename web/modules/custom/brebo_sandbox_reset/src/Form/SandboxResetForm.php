<?php

declare(strict_types=1);

namespace Drupal\brebo_sandbox_reset\Form;

use Drupal\brebo_sandbox_reset\Service\SandboxResetManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Admin-only sandbox content reset form. */
final class SandboxResetForm extends FormBase {

  public function __construct(
    private readonly SandboxResetManager $resetManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_sandbox_reset.manager'));
  }

  public function getFormId(): string {
    return 'brebo_sandbox_reset_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $scope = (string) ($form_state->getValue('scope') ?: 'mail_content');
    $preview = $this->resetManager->preview($scope);

    $form['warning'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--warning"><strong>Alleen inhoud wordt gewist.</strong> Modules, database-schema, velden, rollen, workflows, configuratie en externe bronnen blijven intact. Zoho en Moneybird worden nooit gewijzigd.</div>',
    ];

    $form['scope'] = [
      '#type' => 'radios',
      '#title' => $this->t('Resetbereik'),
      '#options' => [
        'mail_content' => $this->t('Mail-inhoud wissen — broncursors behouden'),
        'mail_content_zoho' => $this->t('Mail-inhoud wissen + Zoho pilot/migratiestate terug naar start'),
      ],
      '#default_value' => $scope,
      '#required' => TRUE,
      '#description' => $this->t('De permanente IMAP-cursor wordt bewust niet teruggezet; een reset mag niet onbedoeld de volledige actuele mailbox opnieuw importeren.'),
    ];

    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Wat wordt verwijderd?'),
      '#open' => TRUE,
    ];
    $form['preview']['counts'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('@count via Mail Intake geregistreerde e-mailcommunicaties', ['@count' => $preview['mail_communications']]),
        $this->t('@count mailboxprojecties', ['@count' => $preview['mailbox_rows']]),
        $this->t('@count mailtags', ['@count' => $preview['mail_tags']]),
        $this->t('@count document/communicatie-koppelingen', ['@count' => $preview['document_links']]),
      ],
    ];
    $form['preview']['kept'] = [
      '#markup' => '<p><strong>Blijft staan:</strong> software, schema, configuratie, mailboxdefinities, gebruikers/rollen, handmatig gemaakte Communication-records zonder Mail Intake source-id en alle externe bronmail.</p>',
    ];

    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik begrijp dat de genoemde sandbox-inhoud definitief uit BREBO Office wordt verwijderd.'),
      '#required' => TRUE,
    ];
    $form['confirmation_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Typ WIS INHOUD ter bevestiging'),
      '#required' => TRUE,
      '#size' => 24,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sandbox-inhoud resetten'),
      '#button_type' => 'danger',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('confirmation_text')) !== 'WIS INHOUD') {
      $form_state->setErrorByName('confirmation_text', $this->t('Bevestiging klopt niet; typ exact WIS INHOUD.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $scope = (string) $form_state->getValue('scope');
    $result = $this->resetManager->reset($scope);

    $this->messenger()->addStatus($this->t(
      'Sandbox-reset voltooid: @communications e-mailcommunicaties en @mailbox mailboxprojecties verwijderd. Software en configuratie zijn behouden.',
      [
        '@communications' => $result['mail_communications'],
        '@mailbox' => $result['mailbox_rows'],
      ],
    ));
  }

}
