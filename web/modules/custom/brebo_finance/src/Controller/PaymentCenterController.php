<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Form\PaymentBatchPrepareForm;
use Drupal\brebo_finance\Service\PayablesWorkQueueBuilder;
use Drupal\brebo_finance\Service\PaymentBatchManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Single operating workbench for supplier invoice-to-payment control. */
final class PaymentCenterController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly PaymentBatchManager $batches,
    private readonly FormBuilderInterface $paymentFormBuilder,
    private readonly PayablesWorkQueueBuilder $workQueues,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_finance.payment_batch_manager'),
      $container->get('form_builder'),
      $container->get('brebo_finance.payables_work_queue_builder'),
    );
  }

  public function page(): array {
    $work = $this->workQueues->build($this->currentUser());
    $queueLabels = [
      'to_code' => ['Te coderen', 'Project, factuurregels of commitmentkoppeling ontbreekt.'],
      'blocked' => ['Geblokkeerd', 'Prestatie-, match- of controlafwijking vraagt eerst oplossing.'],
      'to_match' => ['Te matchen', 'Codering staat; voer de authoritative three-way match uit.'],
      'release_ready' => ['Vrijgave', 'Volledig matched en klaar om betaalvrijgave aan te vragen.'],
      'to_approve' => ['Goedkeuren', 'Betaalvrijgave wacht op onafhankelijke goedkeuring.'],
      'ready_to_pay' => ['Klaar voor betaling', 'Goedgekeurde vrijgave wacht op betalingsuitvoering.'],
    ];

    $batchRows = [];
    if ($this->database->schema()->tableExists('brebo_finance_payment_batch')) {
      $query = $this->database->select('brebo_finance_payment_batch', 'b')
        ->fields('b')
        ->orderBy('created', 'DESC')
        ->range(0, 50);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $batch) {
        $batchId = (int) $batch['id'];
        $actions = [];
        if (in_array((string) $batch['status'], ['draft', 'reviewed'], TRUE)) {
          $actions[] = $this->actionForm('brebo_finance.payment_center_review', ['batch_id' => $batchId], 'Controllercontrole', 'review:' . $batchId);
        }
        if ((string) $batch['status'] === 'reviewed') {
          $actions[] = $this->actionForm('brebo_finance.payment_center_release', ['batch_id' => $batchId], 'Vier-ogen vrijgeven', 'release:' . $batchId, TRUE);
        }
        $batchRows[] = [
          (string) $batch['batch_number'],
          (string) $batch['execution_date'],
          (string) $batch['status'],
          (string) $batch['controller_verdict'],
          $this->money($batch['control_sum']),
          ['data' => ['#markup' => implode(' ', $actions)]],
        ];
      }
    }

    $reconciliationRows = [];
    if ($this->database->schema()->tableExists('brebo_finance_bank_reconciliation')) {
      $query = $this->database->select('brebo_finance_bank_reconciliation', 'r')
        ->fields('r')
        ->orderBy('created', 'DESC')
        ->range(0, 50);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $reconciliationRows[] = [
          strtoupper((string) $row['traffic_light']),
          (string) $row['bank_transaction_id'],
          $this->money(abs((float) $row['amount'])),
          (string) $row['message'],
          (string) $row['moneybird_state'],
        ];
      }
    }

    $abnUrl = 'https://www.abnamro.nl/mijn-abnamro/authenticatie/inloggen/?aabChannel=IBB&aabAuthLevel=low';
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-command-center', 'bfpc']],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Betaalcentrum</h1><p>Eén werkplek van ontvangen inkoopfactuur tot gecontroleerde betaling en reconciliatie.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>',
      ],
      'navigation' => [
        '#markup' => '<div class="bfcc-section"><a class="button" href="' . Url::fromRoute('brebo_finance.command_center_page')->toString() . '">← Dashboard</a> <a class="button" href="#werkvoorraad">Werkvoorraad</a> <a class="button" href="#betaalrun">Betaalrun</a> <a class="button" href="#reconciliatie">Reconciliatie</a> <a class="button" href="' . $abnUrl . '" target="_blank" rel="noopener noreferrer">Open ABN AMRO ↗</a></div>',
      ],
      'work' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-section'], 'id' => 'werkvoorraad'],
        'title' => ['#markup' => '<h2>Werkvoorraad inkoop & betaling</h2><p>Iedere factuur blijft in deze werkbank totdat de volledige controleketen is doorlopen.</p>'],
        'summary' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['bfcc-grid']],
        ],
      ],
    ];

    foreach ($queueLabels as $key => [$title, $description]) {
      $count = (int) ($work['counts'][$key] ?? 0);
      $amount = $this->money($work['amounts_inc_vat'][$key] ?? 0);
      $build['work']['summary'][$key] = [
        '#markup' => '<a class="bfcc-card" href="#queue-' . $key . '"><span class="bfcc-kicker">' . $count . ' items</span><h3>' . $title . '</h3><strong>' . $amount . '</strong><p>' . $description . '</p></a>',
      ];
    }

    foreach ($queueLabels as $key => [$title, $description]) {
      $rows = [];
      foreach ((array) ($work['queues'][$key] ?? []) as $item) {
        $rows[] = [
          Link::fromTextAndUrl((string) $item['invoice_number'], Url::fromRoute('brebo_finance.purchase_invoice_view', ['invoice_id' => $item['invoice_id']])),
          (string) $item['supplier_name'],
          (string) $item['project_label'],
          (string) $item['due_date'],
          (string) $item['priority'],
          $this->money($item['amount_inc_vat']),
          (string) $item['match_status'],
          Link::fromTextAndUrl($this->t('Open factuur →'), Url::fromRoute('brebo_finance.purchase_invoice_view', ['invoice_id' => $item['invoice_id']])),
        ];
      }
      $build['work']['queue_' . $key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfpc-queue'], 'id' => 'queue-' . $key],
        'title' => ['#markup' => '<h3>' . $title . '</h3><p>' . $description . '</p>'],
        'table' => [
          '#type' => 'table',
          '#header' => ['Factuur', 'Leverancier', 'Project', 'Vervaldatum', 'Prioriteit', 'Incl. btw', 'Match', 'Actie'],
          '#rows' => $rows,
          '#empty' => $this->t('Geen items in deze status.'),
        ],
      ];
    }

    $build['prepare'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bfcc-section'], 'id' => 'betaalrun'],
      'title' => ['#markup' => '<h2>Klaar voor betaalrun</h2><p>Alleen goedgekeurde betaalvrijgaven zijn selecteerbaar. Vink regels aan en zet ze gezamenlijk in een betaalbatch.</p>'],
      'form' => $this->paymentFormBuilder->getForm(PaymentBatchPrepareForm::class),
    ];
    $build['batches'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bfcc-section']],
      'title' => ['#markup' => '<h2>Betaalruns</h2>'],
      'table' => [
        '#type' => 'table',
        '#header' => ['Run', 'Uitvoerdatum', 'Status', 'Controller', 'Bedrag', 'Actie'],
        '#rows' => $batchRows,
        '#empty' => $this->t('Nog geen betaalruns.'),
      ],
    ];
    $build['reconciliation'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bfcc-section'], 'id' => 'reconciliatie'],
      'title' => ['#markup' => '<h2>ABN / Moneybird reconciliatie</h2><p>Groen is pas groen als bankuitvoering en boekhoudkundige verwerking aantoonbaar sluiten.</p>'],
      'table' => [
        '#type' => 'table',
        '#header' => ['Stoplicht', 'Bankmutatie', 'Bedrag', 'Oordeel', 'Moneybird'],
        '#rows' => $reconciliationRows,
        '#empty' => $this->t('Nog geen bankreconciliaties.'),
      ],
    ];
    $build['#attached'] = ['library' => ['brebo_finance/command_center']];
    $build['#cache'] = ['max-age' => 0];
    return $build;
  }

  public function prepare(Request $request): RedirectResponse {
    $this->assertToken($request, 'prepare');
    $releaseIds = array_values(array_filter(array_map('intval', (array) $request->request->all('release_ids'))));
    try {
      $batchId = $this->batches->prepare($releaseIds, (string) $request->request->get('execution_date'), (int) $this->currentUser()->id());
      $this->messenger()->addStatus($this->t('Betaalrun @id is voorbereid.', ['@id' => $batchId]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
    return $this->back();
  }

  public function review(int $batch_id, Request $request): RedirectResponse {
    $this->assertToken($request, 'review:' . $batch_id);
    try {
      $result = $this->batches->controllerReview($batch_id, (int) $this->currentUser()->id());
      $this->messenger()->addStatus($this->t('Controllercontrole: @verdict.', ['@verdict' => strtoupper((string) $result['verdict'])]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
    return $this->back();
  }

  public function release(int $batch_id, Request $request): RedirectResponse {
    $this->assertToken($request, 'release:' . $batch_id);
    try {
      $this->batches->release($batch_id, (string) $request->request->get('note'), (int) $this->currentUser()->id());
      $this->messenger()->addStatus($this->t('Betaalrun @id is door de tweede gebruiker vrijgegeven.', ['@id' => $batch_id]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
    return $this->back();
  }

  private function actionForm(string $route, array $parameters, string $label, string $tokenId, bool $note = FALSE): string {
    $url = Url::fromRoute($route, $parameters)->toString();
    $token = $this->csrfToken()->get($tokenId);
    $noteInput = $note ? '<input type="text" name="note" required placeholder="Vrijgavenotitie"> ' : '';
    return '<form method="post" action="' . $url . '" style="display:inline"><input type="hidden" name="token" value="' . $token . '">' . $noteInput . '<button class="button" type="submit">' . $label . '</button></form>';
  }

  private function assertToken(Request $request, string $tokenId): void {
    if (!$this->csrfToken()->validate((string) $request->request->get('token'), $tokenId)) {
      throw new AccessDeniedHttpException('Ongeldige formulierbeveiliging.');
    }
  }

  private function csrfToken(): \Drupal\Core\Access\CsrfTokenGenerator {
    return \Drupal::service('csrf_token');
  }

  private function back(): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('brebo_finance.payment_center')->toString());
  }

  private function money(mixed $value): string {
    return '€ ' . number_format((float) $value, 2, ',', '.');
  }

}
