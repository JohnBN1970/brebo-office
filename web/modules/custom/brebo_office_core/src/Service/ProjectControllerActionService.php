<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\node\NodeInterface;

/**
 * Converts early-warning drivers into concrete project control actions.
 */
final class ProjectControllerActionService {

  public function __construct(
    private readonly ProjectEarlyWarningService $earlyWarning,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function analyze(NodeInterface $project): array {
    $warning = $this->earlyWarning->analyze($project);
    $actions = [];

    foreach ($warning['drivers'] as $driver) {
      $actions[] = $this->actionForDriver($driver);
    }

    usort($actions, static function (array $a, array $b): int {
      $rank = ['kritiek' => 4, 'vandaag' => 3, 'deze_week' => 2, 'monitoren' => 1];
      return ($rank[$b['urgency']] ?? 0) <=> ($rank[$a['urgency']] ?? 0);
    });

    return [
      'score' => $warning['score'],
      'level' => $warning['level'],
      'status' => $warning['status'],
      'top_action' => $actions[0] ?? NULL,
      'actions' => $actions,
      'drivers' => $warning['drivers'],
      'financial_snapshot' => $warning['financial_snapshot'],
    ];
  }

  /** @param array<string, mixed> $driver
   *  @return array<string, mixed>
   */
  private function actionForDriver(array $driver): array {
    $code = (string) ($driver['code'] ?? 'unknown');
    $points = (int) ($driver['points'] ?? 0);
    $value = $driver['value'] ?? 0;
    $urgency = $points >= 20 ? 'kritiek' : ($points >= 10 ? 'vandaag' : ($points >= 5 ? 'deze_week' : 'monitoren'));

    return match ($code) {
      'labor_productivity' => $this->action(
        $code, $points, $urgency, 'Uitvoerder / Projectleider',
        'Controleer productiviteit en resterende personeelsinzet op de werkbegrotingsregels met urenoverschrijding.',
        'Vergelijk ploegbezetting, werkelijke productie en resterende planning; corrigeer dienstplanning of werkmethode waar nodig.',
        'Urenprognose terug binnen budget of expliciete herprognose met onderbouwde maatregel.',
        (float) $value
      ),
      'uncontracted_variations' => $this->action(
        $code, $points, $urgency, 'Projectleider / Contractmanager',
        'Zet openstaand meer-/minderwerk om in een formele opdrachtgeverbeslissing.',
        'Controleer onderbouwing, prijs, impact en akkoordstatus; stuur vandaag op schriftelijke bevestiging wanneer de omzetprognose hiervan afhankelijk is.',
        'Openstaand omzetrisico gecontracteerd, afgewezen of uit de prognose verwijderd.',
        (float) $value
      ),
      'blocked_invoices' => $this->action(
        $code, $points, $urgency, 'Controller / Inkoper',
        'Los alle geblokkeerde leveranciersfacturen op vóór vrijgave voor betaling.',
        'Controleer werkbegroting, inkooporder, factuurbedrag, BTW en G-rekening; corrigeer brondata of leg een expliciete afwijkingsgoedkeuring vast.',
        'Alle facturen hebben geldige 3-way match of aantoonbaar gemotiveerde blokkade.',
        (float) $value
      ),
      'overdue_payables' => $this->action(
        $code, $points, $urgency, 'Controller / Financiële administratie',
        'Beoordeel vervallen goedgekeurde leveranciersfacturen en voorkom onnodige betaalachterstand.',
        'Controleer betaalstatus, betaaltermijn, eventuele blokkade en G-rekeningverdeling; plan alleen betalingen die volledig groen zijn.',
        'Geen onverklaard vervallen goedgekeurde crediteuren.',
        (float) $value
      ),
      'cost_forecast' => $this->action(
        $code, $points, $urgency, 'Projectleider / Calculator',
        'Herleid de geprognosticeerde kostenoverschrijding naar concrete werkbegrotingsregels en neem herstelmaatregelen.',
        'Splits de afwijking uit naar arbeid, inkoop en overige kosten; bepaal per oorzaak besparing, claim, meerwerk of herplanning.',
        'Nieuwe eindkostenprognose met eigenaar en maatregel per afwijkingsbron.',
        (float) $value
      ),
      'margin_leakage' => $this->action(
        $code, $points, $urgency, 'Projectleider / Controller',
        'Voer een margeherstelreview uit voordat verdere verplichtingen worden aangegaan.',
        'Analyseer kostenstijging, productiviteitsverlies, niet-gecontracteerd meerwerk en resterende risico’s; stop of heronderhandel niet-noodzakelijke verplichtingen.',
        'Margeverlies verklaard en voorzien van concrete herstelmaatregelen met financieel effect.',
        (float) $value
      ),
      'expected_loss' => $this->action(
        $code, $points, 'kritiek', 'Directie / Projectleider / Controller',
        'Start direct een project recovery review: het project stuurt op verlies.',
        'Bevries niet-kritieke nieuwe verplichtingen, valideer de eindkostenprognose, contracteer openstaand meerwerk en besluit expliciet over herstelplan en mandaat.',
        'Bestuurlijk vastgesteld herstelplan of bewuste verliesacceptatie met actuele prognose.',
        (float) $value
      ),
      default => $this->action(
        $code, $points, $urgency, 'Projectleider',
        'Onderzoek het controllersignaal en leg oorzaak, maatregel en eigenaar vast.',
        'Controleer de onderliggende projectdata en actualiseer de prognose.',
        'Controllersignaal aantoonbaar opgelost of geaccepteerd.',
        is_numeric($value) ? (float) $value : 0.0
      ),
    };
  }

  /** @return array<string, mixed> */
  private function action(string $code, int $points, string $urgency, string $owner, string $title, string $instruction, string $doneWhen, float $value): array {
    return [
      'code' => $code,
      'points' => $points,
      'urgency' => $urgency,
      'owner' => $owner,
      'title' => $title,
      'instruction' => $instruction,
      'done_when' => $doneWhen,
      'value' => $value,
    ];
  }

}
