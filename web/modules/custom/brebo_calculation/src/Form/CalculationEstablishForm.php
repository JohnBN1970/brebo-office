<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\brebo_calculation\Service\CalculationVersionEstablisher;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Confirms establishment of one calculation version. */
final class CalculationEstablishForm extends ConfirmFormBase {

  private int $calculationId = 0;
  private string $version = '';
  private bool $blocked = FALSE;

  public function __construct(
    private readonly CalculationVersionEstablisher $establisher,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly Connection $database,
    private readonly CalculationAccessGatewayInterface $accessGateway,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.version_establisher'),
      $container->get('brebo_calculation.readiness_inspector'),
      $container->get('database'),
      $container->get('brebo_calculation.access_gateway'),
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
    return Url::fromRoute('brebo_calculation.workbench', ['calculation' => $this->calculationId]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $calculation = NULL): array {
    $this->calculationId = (int) $calculation;
    if ($this->calculationId <= 0) {
      throw new NotFoundHttpException();
    }
    try {
      $this->accessGateway->assertCanEditWorkbench($this->calculationId, (int) $this->currentUser()->id());
    }
    catch (\RuntimeException) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version', 'status', 'locked_at'])
      ->condition('calculation_id', $this->calculationId)
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

    $readiness = $this->readinessInspector->inspect($this->calculationId, $this->version);
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
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => $this->calculationId];

    $form = parent::buildForm($form, $form_state);
    if ($this->blocked) {
      $form['actions']['submit']['#access'] = FALSE;
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->calculationId <= 0) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    $calculationId = $this->calculationId;
    $version = $this->version;
    $this->establisher->establish($calculationId, $version, (int) $this->currentUser()->id());
    $this->messenger()->addStatus('Calculatieversie ' . $version . ' is vastgesteld en vergrendeld.');
    $form_state->setRedirect('brebo_calculation.workbench', ['calculation' => $calculationId]);
  }

}
