<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\brebo_calculation\Service\CalculationVersionEstablisher;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Confirms establishment of one calculation version. */
final class CalculationEstablishForm extends ConfirmFormBase {

  private ?NodeInterface $calculation = NULL;
  private string $version = '';
  private bool $blocked = FALSE;

  public function __construct(
    private readonly CalculationVersionEstablisher $establisher,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.version_establisher'),
      $container->get('brebo_calculation.readiness_inspector'),
      $container->get('database'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_establish_form';
  }

  public function getQuestion(): string {
    return 'Calculatieversie definitief vaststellen?';
  }

  public function getDescription(): string {
    return 'Hiermee wordt deze versie vergrendeld en als immutable snapshot opgeslagen. De versie kan daarna niet meer worden gewijzigd.';
  }

  public function getConfirmText(): string {
    return 'Versie vaststellen';
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_office_core.calculation_dashboard', ['node' => $this->calculation?->id() ?? 0]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update', $this->currentUser())) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    $this->calculation = $node;

    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version', 'status', 'locked_at'])
      ->condition('calculation_id', (int) $node->id())
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException('Calculatieversie niet gevonden.');
    }

    $this->version = (string) $row['version'];
    if ((string) $row['status'] !== 'draft' || $row['locked_at'] !== NULL) {
      throw new \RuntimeException('Alleen een open conceptversie kan worden vastgesteld.');
    }

    $readiness = $this->readinessInspector->inspect((int) $node->id(), $this->version);
    if ((int) ($readiness['blocking'] ?? 0) > 0) {
      $this->blocked = TRUE;
      $form['blocked'] = [
        '#markup' => '<div class="messages messages--error"><strong>Vaststellen geblokkeerd.</strong> Los eerst '
          . (int) $readiness['blocking'] . ' readiness-blokkade(s) op.</div>',
      ];
    }

    if ((int) ($readiness['warnings'] ?? 0) > 0) {
      $form['warnings'] = [
        '#markup' => '<div class="messages messages--warning">Deze versie bevat '
          . (int) $readiness['warnings'] . ' waarschuwing(en). Vaststellen is toegestaan, maar controleer deze bewust.</div>',
      ];
    }

    $form['version_info'] = [
      '#markup' => '<p><strong>Versie:</strong> ' . htmlspecialchars($this->version) . '</p>',
    ];
    $form['version'] = ['#type' => 'hidden', '#value' => $this->version];
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => (int) $node->id()];

    $form = parent::buildForm($form, $form_state);
    if ($this->blocked) {
      $form['actions']['submit']['#access'] = FALSE;
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->calculation instanceof NodeInterface || !$this->calculation->access('update', $this->currentUser())) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    $calculationId = (int) $this->calculation->id();
    $version = $this->version;
    $this->establisher->establish($calculationId, $version, $this->currentUser());
    $this->messenger()->addStatus('Calculatieversie ' . $version . ' is vastgesteld en vergrendeld.');
    $form_state->setRedirect('brebo_office_core.calculation_dashboard', ['node' => $calculationId]);
  }

}
