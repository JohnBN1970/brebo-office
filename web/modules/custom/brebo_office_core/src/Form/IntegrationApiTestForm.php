<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\IntegrationApiClientInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulier voor een uitsluitend fictieve Integration API-test.
 */
final class IntegrationApiTestForm extends FormBase {

  public function __construct(
    private readonly IntegrationApiClientInterface $client,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_office_core.integration_api_client'),
    );
  }

  public function getFormId(): string {
    return 'brebo_office_core_integration_api_test';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form['warning'] = [
      '#type' => 'status_messages',
    ];

    $form['explanation'] = [
      '#type' => 'container',
      'text' => [
        '#markup' => $this->t(
          '<p><strong>Uitsluitend voor fictieve testgegevens.</strong> '
          . 'Voer geen namen, adressen, contactgegevens, projectgegevens, '
          . 'bedrijfsinformatie of andere echte gegevens in. '
          . 'De analyse wordt niet opgeslagen, verzonden of formeel vastgesteld.</p>',
        ),
      ],
    ];

    $form['communication'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fictieve testcommunicatie'),
    ];

    $form['communication']['channel'] = [
      '#type' => 'select',
      '#title' => $this->t('Fictief communicatiekanaal'),
      '#options' => [
        'email' => $this->t('E-mail'),
        'whatsapp' => $this->t('WhatsApp'),
        'telephone_note' => $this->t('Telefoonnotitie'),
        'other' => $this->t('Overig'),
      ],
      '#required' => TRUE,
    ];

    $form['communication']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fictief onderwerp'),
      '#required' => TRUE,
      '#maxlength' => 200,
      '#description' => $this->t('Maximaal 200 tekens.'),
    ];

    $form['communication']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fictief bericht'),
      '#required' => TRUE,
      '#rows' => 10,
      '#maxlength' => 4000,
      '#description' => $this->t('Maximaal 4.000 tekens.'),
    ];

    $form['fictional_confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t(
        'Ik bevestig dat alle ingevoerde gegevens volledig fictief zijn.',
      ),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fictieve testanalyse uitvoeren'),
      '#button_type' => 'primary',
    ];

    $result = $form_state->get('integration_api_test_result');

    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Testresultaat'),
        '#open' => TRUE,
        '#weight' => 100,
      ];

      $form['result']['metadata'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Onderdeel'),
          $this->t('Waarde'),
        ],
        '#rows' => [
          [
            $this->t('Status'),
            (string) ($result['state'] ?? 'unknown'),
          ],
          [
            $this->t('HTTP-status'),
            $result['http_status'] === NULL
              ? $this->t('Niet beschikbaar')
              : (string) $result['http_status'],
          ],
          [
            $this->t('Responstijd'),
            $result['response_time_ms'] === NULL
              ? $this->t('Niet beschikbaar')
              : $this->t('@milliseconds ms', [
                '@milliseconds' => $result['response_time_ms'],
              ]),
          ],
          [
            $this->t('Gecontroleerd op'),
            (string) ($result['checked_at'] ?? ''),
          ],
        ],
      ];

      if (
        ($result['state'] ?? NULL) === 'completed'
        && is_array($result['analysis'] ?? NULL)
      ) {
        $encoded = json_encode(
          $result['analysis'],
          JSON_PRETTY_PRINT
          | JSON_UNESCAPED_UNICODE
          | JSON_UNESCAPED_SLASHES,
        );

        $form['result']['review_warning'] = [
          '#markup' => $this->t(
            '<p><strong>Menselijke beoordeling is verplicht.</strong> '
            . 'Deze testuitvoer is geen besluit, advies of vaststelling.</p>',
          ),
        ];

        $form['result']['analysis'] = [
          '#prefix' => '<pre class="brebo-integration-test-analysis">',
          '#plain_text' => $encoded === FALSE
            ? $this->t('De analyse kon niet worden weergegeven.')
            : $encoded,
          '#suffix' => '</pre>',
        ];
      }
      else {
        $form['result']['failure'] = [
          '#plain_text' => $this->stateMessage(
            (string) ($result['state'] ?? 'unknown'),
          ),
        ];
      }
    }

    return $form;
  }

  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    if ($form_state->getValue('fictional_confirmation') !== 1) {
      $form_state->setErrorByName(
        'fictional_confirmation',
        $this->t('Bevestig dat de invoer volledig fictief is.'),
      );
    }
  }

  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $result = $this->client->analyzeTestCommunication([
      'channel' => (string) $form_state->getValue('channel'),
      'subject' => (string) $form_state->getValue('subject'),
      'message' => (string) $form_state->getValue('message'),
    ]);

    $form_state->set('integration_api_test_result', $result);
    $form_state->setRebuild(TRUE);

    if (($result['state'] ?? NULL) === 'completed') {
      $this->messenger()->addStatus(
        $this->t('De fictieve testanalyse is voltooid.'),
      );
    }
    else {
      $this->messenger()->addWarning(
        $this->stateMessage((string) ($result['state'] ?? 'unknown')),
      );
    }
  }

  private function stateMessage(string $state): string {
    return match ($state) {
      'not_configured' => (string) $this->t(
        'De Integration API is nog niet geconfigureerd.',
      ),
      'invalid_input' => (string) $this->t(
        'De fictieve testinvoer is ongeldig of te lang.',
      ),
      'rejected' => (string) $this->t(
        'De Integration API heeft de testaanvraag geweigerd.',
      ),
      'invalid_response' => (string) $this->t(
        'De Integration API gaf geen geldige, veilige testrespons.',
      ),
      'unreachable' => (string) $this->t(
        'De Integration API is momenteel niet bereikbaar.',
      ),
      default => (string) $this->t(
        'De fictieve testanalyse kon niet worden voltooid.',
      ),
    };
  }

}
