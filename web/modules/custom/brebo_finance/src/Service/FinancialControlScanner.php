<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/**
 * Produces cause-and-effect findings from the financial project state.
 */
final class FinancialControlScanner {

  private const string SOURCE = 'automatic_financial_control';

  public function __construct(
    private readonly Connection $database,
    private readonly LabourProductivityManager $labourProductivityManager,
  ) {}

  /**
   * Scans one project and returns counts by severity.
   *
   * @return array<string, int>
   */
  public function scanProject(int $projectNid): array {
    $now = time();
    $today = date('Y-m-d', $now);
    $seen = [];
    $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

    if (!$this->hasLockedBudget($projectNid)) {
      $this->record($projectNid, 'FIN-BASELINE-MISSING', 'critical', 'project', $projectNid,
        'Goedgekeurde werkbegroting ontbreekt',
        'De werkbegroting is nog niet multidisciplinair goedgekeurd en vergrendeld.',
        'Inkoop, resultaatbewaking en prognoses hebben geen betrouwbare kostenbaseline.',
        'Laat calculator/inkoper, werkvoorbereider en projectleider de werkbegroting controleren en goedkeuren.',
        $now,
      );
      $seen[] = $this->key('FIN-BASELINE-MISSING', 'project', $projectNid);
      $counts['critical']++;
    }

    if ($this->hasLockedBudget($projectNid)) {
      $labour = $this->labourProductivityManager->analyzeProject($projectNid);
      foreach ($labour['lines'] as $line) {
        if ($line['status'] === 'forecast_overrun') {
          $this->record($projectNid, 'FIN-LABOUR-FORECAST-OVERRUN', 'high', 'budget_line', (int) $line['budget_line_id'],
            'Verwachte einduren overschrijden het urenbudget',
            'Werkelijke productiviteit of resterende personeelsplanning wijkt af van de goedgekeurde werkbegroting.',
            'De verwachte arbeidskosten en projectmarge verslechteren wanneer niet wordt bijgestuurd.',
            'Controleer voortgang en bronuren, bepaal de operationele oorzaak en stuur planning, ploegbezetting of uitvoeringsmethode aantoonbaar bij.',
            $now,
            [
              'budget_hours' => $line['budget_hours'],
              'planned_hours' => $line['planned_hours'],
              'actual_approved_hours' => $line['actual_approved_hours'],
              'forecast_end_hours' => $line['forecast_end_hours'],
              'forecast_impact_ex_vat' => $line['forecast_variance_ex_vat'],
              'work_package' => $line['work_package'],
            ],
          );
          $seen[] = $this->key('FIN-LABOUR-FORECAST-OVERRUN', 'budget_line', (int) $line['budget_line_id']);
          $counts['high']++;
        }
        elseif ($line['status'] === 'planning_overrun') {
          $this->record($projectNid, 'FIN-LABOUR-PLANNING-OVERRUN', 'medium', 'budget_line', (int) $line['budget_line_id'],
            'Geplande personeelsuren overschrijden het urenbudget',
            'De bevestigde of voorlopige personeelsinzet is hoger dan de vrijgegeven uren op de werkbegrotingsregel.',
            'Zonder correctie ontstaat waarschijnlijk een arbeidskostenoverschrijding.',
            'Herplan de inzet of leg vóór uitvoering een onderbouwde en goedgekeurde budgetmutatie vast.',
            $now,
            [
              'budget_hours' => $line['budget_hours'],
              'planned_hours' => $line['planned_hours'],
              'forecast_impact_ex_vat' => $line['forecast_variance_ex_vat'],
              'work_package' => $line['work_package'],
            ],
          );
          $seen[] = $this->key('FIN-LABOUR-PLANNING-OVERRUN', 'budget_line', (int) $line['budget_line_id']);
          $counts['medium']++;
        }
      }
    }

    $invoiceQuery = $this->database->select('brebo_finance_purchase_invoice', 'i');
    $invoiceQuery->fields('i', ['id', 'invoice_number', 'match_status', 'status', 'due_date', 'amount_inc_vat']);
    $invoiceQuery->condition('project_nid', $projectNid);
    $invoiceQuery->condition('status', ['cancelled', 'paid'], 'NOT IN');
    foreach ($invoiceQuery->execute()->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
      if ($invoice['match_status'] === 'exception') {
        $this->record($projectNid, 'FIN-INVOICE-EXCEPTION', 'high', 'purchase_invoice', (int) $invoice['id'],
          'Inkoopfactuur bevat een afwijking',
          'Opdracht, geverifieerde prestatie, prijs, hoeveelheid of btw komt niet overeen met de factuur.',
          'Onterechte betaling, budgetoverschrijding of fiscale correctie kan ontstaan.',
          'Onderzoek de afwijkingscodes per factuurregel en corrigeer of keur de afwijking gemotiveerd af.',
          $now,
          ['invoice_number' => $invoice['invoice_number']],
        );
        $seen[] = $this->key('FIN-INVOICE-EXCEPTION', 'purchase_invoice', (int) $invoice['id']);
        $counts['high']++;
      }
      elseif ($invoice['match_status'] !== 'matched') {
        $this->record($projectNid, 'FIN-INVOICE-UNMATCHED', 'medium', 'purchase_invoice', (int) $invoice['id'],
          'Inkoopfactuur is nog niet volledig gematcht',
          'De driestapscontrole is nog niet afgerond.',
          'De betaaltermijn loopt terwijl betaling terecht geblokkeerd blijft.',
          'Koppel alle factuurregels aan opdracht en geverifieerde prestatie en voer de match opnieuw uit.',
          $now,
          ['invoice_number' => $invoice['invoice_number']],
        );
        $seen[] = $this->key('FIN-INVOICE-UNMATCHED', 'purchase_invoice', (int) $invoice['id']);
        $counts['medium']++;
      }

      if (!empty($invoice['due_date']) && $invoice['due_date'] < $today) {
        $this->record($projectNid, 'FIN-INVOICE-OVERDUE', 'high', 'purchase_invoice', (int) $invoice['id'],
          'Vervallen inkoopfactuur is niet betaald',
          'De vervaldatum is verstreken terwijl de factuur niet als betaald is bevestigd.',
          'Aanmaningen, rente, leveranciersproblemen of bouwvertraging kunnen ontstaan.',
          'Bepaal of de blokkade terecht is, communiceer met de leverancier en rond controle of betaling af.',
          $now,
          ['invoice_number' => $invoice['invoice_number'], 'due_date' => $invoice['due_date']],
        );
        $seen[] = $this->key('FIN-INVOICE-OVERDUE', 'purchase_invoice', (int) $invoice['id']);
        $counts['high']++;
      }
    }

    $mutationLimit = $now - (7 * 86400);
    $mutationQuery = $this->database->select('brebo_finance_budget_mutation', 'm');
    $mutationQuery->fields('m', ['id', 'mutation_number', 'amount_ex_vat', 'created']);
    $mutationQuery->condition('project_nid', $projectNid);
    $mutationQuery->condition('status', ['draft', 'in_review'], 'IN');
    $mutationQuery->condition('created', $mutationLimit, '<');
    foreach ($mutationQuery->execute()->fetchAll(\PDO::FETCH_ASSOC) as $mutation) {
      $this->record($projectNid, 'FIN-MUTATION-PENDING', 'medium', 'budget_mutation', (int) $mutation['id'],
        'Budgetmutatie wacht langer dan zeven dagen',
        'De financiële afwijking is nog niet goedgekeurd of afgewezen.',
        'Inkoop kan geblokkeerd blijven en de actuele prognose kan onvolledig zijn.',
        'Beoordeel oorzaak, gevolg en beheersmaatregel en neem een gemotiveerd besluit.',
        $now,
        ['mutation_number' => $mutation['mutation_number'], 'amount_ex_vat' => $mutation['amount_ex_vat']],
      );
      $seen[] = $this->key('FIN-MUTATION-PENDING', 'budget_mutation', (int) $mutation['id']);
      $counts['medium']++;
    }

    $gQuery = $this->database->select('brebo_finance_g_account_instruction', 'g');
    $gQuery->fields('g', ['id', 'effective_until', 'counterparty_name']);
    $gQuery->condition('project_nid', $projectNid);
    $gQuery->condition('status', 'approved');
    $gQuery->isNotNull('effective_until');
    $gQuery->condition('effective_until', $today, '<');
    foreach ($gQuery->execute()->fetchAll(\PDO::FETCH_ASSOC) as $instruction) {
      $this->record($projectNid, 'FIN-GACCOUNT-EXPIRED', 'high', 'g_account_instruction', (int) $instruction['id'],
        'G-rekeningsinstructie is verlopen',
        'De goedgekeurde geldigheidsperiode is verstreken.',
        'Een betaling kan verkeerd worden gesplitst en het ketenaansprakelijkheidsrisico kan toenemen.',
        'Controleer de actuele overeenkomst en leg vóór betaalvrijgave een nieuwe goedgekeurde instructie vast.',
        $now,
        ['counterparty' => $instruction['counterparty_name'], 'effective_until' => $instruction['effective_until']],
      );
      $seen[] = $this->key('FIN-GACCOUNT-EXPIRED', 'g_account_instruction', (int) $instruction['id']);
      $counts['high']++;
    }

    $latestForecast = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f', ['snapshot_date'])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $forecastLimit = date('Y-m-d', $now - (30 * 86400));
    if ($latestForecast === FALSE || $latestForecast < $forecastLimit) {
      $this->record($projectNid, 'FIN-FORECAST-STALE', 'medium', 'project', $projectNid,
        'Financiële eindprognose ontbreekt of is verouderd',
        'Er is geen prognose van de laatste dertig dagen.',
        'Margeverlies en nieuwe risico’s kunnen te laat zichtbaar worden.',
        'Actualiseer resterende kosten, risicoreserve en verwachte eindkosten.',
        $now,
        ['latest_snapshot_date' => $latestForecast !== FALSE ? $latestForecast : NULL],
      );
      $seen[] = $this->key('FIN-FORECAST-STALE', 'project', $projectNid);
      $counts['medium']++;
    }

    $this->resolveDisappeared($projectNid, $seen, $now);
    return $counts;
  }

  private function hasLockedBudget(int $projectNid): bool {
    return (bool) $this->database->select('brebo_finance_budget', 'b')
      ->condition('project_nid', $projectNid)
      ->condition('budget_type', 'working')
      ->condition('status', 'locked')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  private function record(
    int $projectNid,
    string $code,
    string $severity,
    string $sourceType,
    int $sourceId,
    string $title,
    string $cause,
    string $consequence,
    string $measure,
    int $now,
    array $payload = [],
  ): void {
    $currentStatus = $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', ['status'])
      ->condition('project_nid', $projectNid)
      ->condition('control_code', $code)
      ->condition('source_type', $sourceType)
      ->condition('source_id', $sourceId)
      ->execute()
      ->fetchField();
    $pendingVerification = $currentStatus === 'pending_verification';

    $fields = [
      'origin' => self::SOURCE,
      'severity' => $severity,
      'title' => $title,
      'cause' => $cause,
      'consequence' => $consequence,
      'control_measure' => $measure,
      'status' => $pendingVerification ? 'pending_verification' : 'open',
      'last_seen' => $now,
      'payload' => $payload !== [] ? json_encode($payload, JSON_THROW_ON_ERROR) : NULL,
      'changed' => $now,
    ];
    if (!$pendingVerification) {
      $fields += [
        'resolved' => NULL,
        'resolved_by' => NULL,
        'resolution_note' => NULL,
        'resolution_evidence' => NULL,
        'resolution_submitted_by' => NULL,
        'resolution_verified_by' => NULL,
      ];
    }

    $this->database->merge('brebo_finance_control_finding')
      ->keys([
        'project_nid' => $projectNid,
        'control_code' => $code,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
      ])
      ->insertFields([
        'detected' => $now,
        'created' => $now,
      ])
      ->fields($fields)
      ->execute();
  }

  /**
   * Automatically resolves findings whose underlying condition disappeared.
   *
   * @param list<string> $seen
   */
  private function resolveDisappeared(int $projectNid, array $seen, int $now): void {
    $query = $this->database->select('brebo_finance_control_finding', 'f');
    $query->fields('f', ['id', 'control_code', 'source_type', 'source_id']);
    $query->condition('project_nid', $projectNid);
    $query->condition('status', 'open');
    $query->condition('origin', self::SOURCE);
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $finding) {
      $key = $this->key($finding['control_code'], $finding['source_type'], (int) $finding['source_id']);
      if (!in_array($key, $seen, TRUE)) {
        $this->database->update('brebo_finance_control_finding')
          ->fields([
            'status' => 'resolved_automatically',
            'resolved' => $now,
            'resolution_note' => 'The underlying control condition is no longer present.',
            'changed' => $now,
          ])
          ->condition('id', $finding['id'])
          ->execute();
      }
    }
  }

  private function key(string $code, string $sourceType, int $sourceId): string {
    return "$code|$sourceType|$sourceId";
  }

}
