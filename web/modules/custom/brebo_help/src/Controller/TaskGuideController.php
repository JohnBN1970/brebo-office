<?php

declare(strict_types=1);

namespace Drupal\brebo_help\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TaskGuideController extends ControllerBase {

  public function index(): array {
    $cards = '';
    foreach ($this->guides() as $id => $guide) {
      $url = Url::fromRoute('brebo_help.task', ['task' => $id]);
      $cards .= '<article class="brebo-help__card"><h3>' . Link::fromTextAndUrl($guide['title'], $url)->toString() . '</h3><p>' . $guide['summary'] . '</p><div class="brebo-help__meta">Taakhandleiding</div></article>';
    }
    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<article class="brebo-help"><p class="brebo-help__intro">Praktische werkwijzen voor handelingen die in BREBO Office dagelijks terugkomen.</p><div class="brebo-help__grid">' . $cards . '</div><p class="brebo-help__back">' . Link::fromTextAndUrl('← Terug naar Help', Url::fromRoute('brebo_help.center'))->toString() . '</p></article>',
    ];
  }

  public function guide(string $task): array {
    $guides = $this->guides();
    if (!isset($guides[$task])) {
      throw new NotFoundHttpException();
    }
    $guide = $guides[$task];
    $steps = '';
    foreach ($guide['steps'] as $step) {
      $steps .= '<li>' . $step . '</li>';
    }
    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<article class="brebo-help"><p class="brebo-help__meta">Dagelijkse taak · ' . $guide['owner'] . '</p><p class="brebo-help__intro">' . $guide['summary'] . '</p><h2>Werkwijze</h2><ol class="brebo-help__steps">' . $steps . '</ol><h2>Controlepunt</h2><p>' . $guide['control'] . '</p><h2>Waarom BREBO dit zo doet</h2><p>' . $guide['why'] . '</p><p class="brebo-help__back">' . Link::fromTextAndUrl('← Alle dagelijkse taken', Url::fromRoute('brebo_help.tasks'))->toString() . '</p></article>',
    ];
  }

  public function title(string $task): string {
    $guides = $this->guides();
    if (!isset($guides[$task])) {
      throw new NotFoundHttpException();
    }
    return $guides[$task]['title'];
  }

  private function guides(): array {
    return [
      'project-vrijgeven' => $this->item('Een project gereedmaken voor uitvoering', 'Controleer vóór de start of scope, gebouw, contract, werkbegroting, planning en verantwoordelijkheden voldoende vastliggen.', 'Projectleider / Werkvoorbereider', ['Open het project en controleer opdrachtgever, gebouw en contractuele scope.', 'Controleer of de werkbegroting is goedgekeurd en de uitvoeringsbasis klopt.', 'Controleer planning, verantwoordelijken, documenten en noodzakelijke inkoop.', 'Leg ontbrekende voorwaarden als open actie of afwijking vast.', 'Geef uitvoering pas vrij wanneer kritieke blokkades zijn opgelost.'], 'Geen uitvoering vrijgeven zolang een kritieke scope-, budget-, document- of planningsvoorwaarde ontbreekt.', 'Een beheerste projectstart voorkomt dat problemen uit de voorbereiding als faalkosten op de bouw terechtkomen.'),
      'afwijking-verwerken' => $this->item('Een projectafwijking verwerken', 'Maak een afwijking zichtbaar op het moment dat zij ontstaat en beoordeel direct gevolgen voor scope, tijd, geld en kwaliteit.', 'Projectleider / Uitvoerder', ['Leg de feitelijke afwijking vast bij het juiste projectonderdeel.', 'Voeg foto’s, communicatie of ander bewijs toe.', 'Beoordeel oorzaak, gevolg, urgentie en impact op planning en werkbegroting.', 'Wijs besluit en herstelactie toe aan een verantwoordelijke.', 'Sluit de afwijking pas nadat maatregel en gevolg aantoonbaar zijn verwerkt.'], 'Een afwijking is niet afgehandeld omdat erover is gesproken; besluit, uitvoering en bewijs moeten herleidbaar zijn.', 'BREBO wil afwijkingen beheersen vóórdat zij onzichtbaar doorwerken in kosten, planning of opleverkwaliteit.'),
      'inkoop-controleren' => $this->item('Inkoop controleren vóór opdracht', 'Controleer een leveranciers- of onderaannemersverplichting inhoudelijk én financieel voordat BREBO zich bindt.', 'Calculator/Inkoper / Projectleider', ['Controleer leverancier, project en gevraagde scope.', 'Vergelijk offerte met calculatie en goedgekeurde werkbegroting.', 'Controleer uitsluitingen, hoeveelheden, voorwaarden, levertijd, BTW en betaalafspraken.', 'Beoordeel budgetafwijking en leg noodzakelijke goedkeuring vast.', 'Leg de verplichting pas vast nadat de controle compleet is.'], 'Geen opdracht uitsluitend op totaalprijs beoordelen; scopeverschillen en uitsluitingen moeten expliciet zijn gecontroleerd.', 'De financiële controller moet een afwijking kunnen signaleren vóór de factuur en niet pas erna.'),
      'restpunt-afronden' => $this->item('Een restpunt aantoonbaar afronden', 'Van constatering naar herstel en onafhankelijke nacontrole zonder bewijsverlies.', 'Uitvoerder / KAM-Kwaliteit', ['Controleer locatie, gebrek, oorzaak, gevolg en urgentie.', 'Leg foto’s en herstelverantwoordelijke vast.', 'Voer of laat het herstel uitvoeren.', 'Maak nieuw bewijs van de herstelde situatie.', 'Laat de nacontrole uitvoeren en sluit het punt pas na akkoord.'], 'De persoon die herstel meldt, bepaalt niet automatisch dat de kwaliteitscontrole is geslaagd.', 'Onafhankelijke nacontrole voorkomt cosmetisch sluiten van punten en beperkt klachten en garantieschade.'),
      'werkdag-controleren' => $this->item('De werkdag op de bouw controleren', 'Maak dagelijkse projectsturing op afstand mogelijk met vaste digitale controlepunten.', 'Uitvoerder / Projectleider', ['Controleer geplande activiteiten, diensten en aanwezige mensen.', 'Controleer of materiaal, informatie en werkvoorwaarden beschikbaar zijn.', 'Leg voortgang en relevante situaties gedurende de dag vast.', 'Registreer afwijkingen, veiligheids- en kwaliteitspunten direct.', 'Controleer aan het einde van de dag uren, voortgang, open punten en gevolgen voor morgen.'], 'Een mondelinge dagupdate vervangt de objectieve projectregistratie niet.', 'BREBO werkt met ingehuurde vakmensen en moet projecten ook zonder permanente fysieke aanwezigheid aantoonbaar kunnen sturen.'),
      'document-vrijgeven' => $this->item('Een document als geldende projectinformatie gebruiken', 'Voorkom dat uitvoering werkt met een losse, oude of niet-herleidbare versie.', 'Werkvoorbereider / Projectleider', ['Controleer bij welk gebouw, project en onderdeel het document hoort.', 'Controleer documenttype, datum, versie en herkomst.', 'Beoordeel of het document daadwerkelijk voor uitvoering bedoeld is.', 'Koppel het document aan de juiste Office-context.', 'Communiceer wijzigingen via de projectcontext en voorkom parallelle losse kopieën.'], 'Bij twijfel over versie of status wordt het document niet als geldende uitvoeringsinformatie beschouwd.', 'Eén herleidbare documentbron beperkt uitvoeringsfouten en discussie over welke informatie geldig was.'),
      'financiele-afwijking' => $this->item('Een financiële afwijking beslissen', 'Zet een financieel signaal om in een expliciet, onderbouwd en opvolgbaar besluit.', 'Projectleider / Financiële controle', ['Open de afwijking vanuit het project of Finance.', 'Controleer bronbedrag, werkbegroting, verplichtingen en reeds bekende gevolgen.', 'Bepaal impact op projectresultaat en cashflow.', 'Leg besluit, onderbouwing, verantwoordelijke en vervolgactie vast.', 'Controleer na uitvoering of de financiële positie daadwerkelijk is bijgewerkt.'], 'Een afwijking blijft open zolang het besluit of de financiële verwerking niet aantoonbaar is afgerond.', 'Besluiten moeten reproduceerbaar zijn zodat BREBO niet afhankelijk wordt van mondelinge kennis.'),
    ];
  }

  private function item(string $title, string $summary, string $owner, array $steps, string $control, string $why): array {
    return compact('title', 'summary', 'owner', 'steps', 'control', 'why');
  }
}
