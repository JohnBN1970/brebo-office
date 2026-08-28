<?php

declare(strict_types=1);

namespace Drupal\brebo_help\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GlossaryController extends ControllerBase {

  public function index(): array {
    $cards = '';
    foreach ($this->terms() as $id => $term) {
      $cards .= '<article class="brebo-help__card"><h3>' . Link::fromTextAndUrl($term['title'], Url::fromRoute('brebo_help.term', ['term' => $id]))->toString() . '</h3><p>' . $term['summary'] . '</p><div class="brebo-help__meta">Begrip / beslisregel</div></article>';
    }
    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<div class="brebo-help"><p class="brebo-help__intro">Kernbegrippen en beslisregels die bepalen hoe BREBO Office wordt gebruikt.</p><div class="brebo-help__grid">' . $cards . '</div></div>',
    ];
  }

  public function term(string $term): array {
    $terms = $this->terms();
    if (!isset($terms[$term])) {
      throw new NotFoundHttpException();
    }
    $item = $terms[$term];
    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<article class="brebo-help"><p class="brebo-help__meta">Begrip / beslisregel</p><p class="brebo-help__intro">' . $item['summary'] . '</p><h2>Wat betekent dit?</h2><p>' . $item['meaning'] . '</p><h2>Wanneer gebruik je dit?</h2><p>' . $item['use'] . '</p><h2>BREBO-regel</h2><p>' . $item['rule'] . '</p><p class="brebo-help__back">' . Link::fromTextAndUrl('← Terug naar Begrippen', Url::fromRoute('brebo_help.glossary'))->toString() . '</p></article>',
    ];
  }

  public function title(string $term): string {
    $terms = $this->terms();
    if (!isset($terms[$term])) {
      throw new NotFoundHttpException();
    }
    return $terms[$term]['title'];
  }

  private function terms(): array {
    return [
      'werkbegroting' => $this->item('Werkbegroting', 'De goedgekeurde operationele financiële basis van een project.', 'De werkbegroting vertaalt de commerciële uitgangspunten naar de kosten- en budgetstructuur waarop uitvoering wordt gestuurd.', 'Bij projectstart, inkoop, afwijkingen, nacalculatie en financiële besluitvorming.', 'Ga geen projectverplichting aan zonder de relevante goedgekeurde werkbegrotingsbasis te controleren.'),
      'stelpost' => $this->item('Stelpost', 'Een voorlopig bedrag voor werk waarvan omvang of keuze nog niet definitief vaststaat.', 'Een stelpost is geen vaste aanneemsom voor het betreffende onderdeel; het uiteindelijke verschil moet beheerst worden.', 'Wanneer het onderdeel wel tot de scope behoort maar nog niet voldoende bepaalbaar is.', 'Leg uitgangspunt, bedrag en latere verrekening expliciet vast.'),
      'verrekenpost' => $this->item('Verrekenpost', 'Een post waarvan het uiteindelijke bedrag op basis van werkelijke hoeveelheid of afspraak wordt afgerekend.', 'De prijs of eenheid is bekend, maar de definitieve omvang wordt later vastgesteld.', 'Bij variabele hoeveelheden of vooraf overeengekomen verrekenmechanismen.', 'Bewaar de meet- of bewijsbasis waarop de uiteindelijke verrekening rust.'),
      'optie' => $this->item('Optie', 'Een afzonderlijk aangeboden onderdeel dat niet automatisch tot de opdracht behoort.', 'Een optie maakt alternatieven of aanvullingen zichtbaar zonder ze stilzwijgend in de basisscope op te nemen.', 'Bij aanvullende keuzes die de opdrachtgever expliciet kan accepteren of afwijzen.', 'Behandel een optie pas als opdracht nadat acceptatie aantoonbaar is vastgelegd.'),
      'btw-verlegd' => $this->item('BTW verlegd', 'Een fiscale verleggingsregeling waarbij de BTW-heffing naar de afnemer verschuift.', 'Office moet onderscheid maken tussen normale BTW en een geldige verleggingssituatie.', 'Alleen wanneer de transactie daadwerkelijk aan de geldende voorwaarden voor verlegging voldoet.', 'Gebruik BTW verlegd nooit als standaardkeuze; leg de fiscale grondslag correct vast.'),
      'g-rekening' => $this->item('G-rekening', 'Een geblokkeerde rekening die binnen relevante keten- of inlenersaansprakelijkheid kan worden gebruikt.', 'Een afgesproken deel van een betaling kan naar de G-rekening gaan om risico rond loonheffingen en BTW te beheersen.', 'Wanneer contract, leverancier/onderaannemer en fiscale situatie dit vereisen of wenselijk maken.', 'Leg percentage of bedrag en betaalafspraak expliciet vast; behandel dit niet als vrije betaalmethode.'),
      'kostprijs' => $this->item('Kostprijs', 'De interne kostenbasis vóór commerciële marge.', 'Kostprijs omvat de kosten die BREBO verwacht te maken voor arbeid, materiaal, inkoop en toegerekende projectkosten volgens de gekozen calculatiestructuur.', 'Bij calculatie, prijscontrole, werkbegroting en nacalculatie.', 'Houd kostprijs gescheiden van verkoopprijs en reserveringsprijs.'),
      'marge' => $this->item('Marge', 'Het commerciële verschil boven de kostprijs volgens de gekozen prijsopbouw.', 'Marge maakt zichtbaar welk deel van de verkoopprijs niet direct uit onderliggende kostprijs bestaat.', 'Bij calculatie, offerte en bewaking van commerciële uitgangspunten.', 'Wijzig marge bewust en herleidbaar; verberg afwijkingen niet door kostprijzen kunstmatig aan te passen.'),
      'afwijking' => $this->item('Afwijking', 'Een aantoonbaar verschil tussen afgesproken, begrote, geplande of vereiste situatie en de werkelijkheid.', 'Een afwijking kan betrekking hebben op scope, geld, planning, kwaliteit, veiligheid of uitvoering.', 'Zodra een relevante werkelijkheid afwijkt van de geldende projectbasis.', 'Registreer vroeg, bepaal impact, wijs eigenaar toe en sluit pas na besluit en controle.'),
      'restpunt' => $this->item('Restpunt', 'Een nog niet aantoonbaar gereed of akkoord bevonden onderdeel.', 'Een restpunt blijft open totdat herstel of afronding is uitgevoerd én gecontroleerd.', 'Bij inspecties, tussentijdse controles, oplevering en herstel.', 'Een melding of foto van herstel is geen automatische sluiting; nacontrole is leidend.'),
      'projectcockpit' => $this->item('Projectcockpit', 'Het centrale stuurbeeld van een lopend project.', 'De cockpit brengt bestaande projectinformatie, signalen en koppelingen samen zonder een tweede administratie te creëren.', 'Voor dagelijkse en periodieke projectsturing.', 'Stuur vanuit gekoppelde bronobjecten en leg besluiten terug op het project.'),
      'office-bron' => $this->item('Office als bron', 'BREBO Office is de primaire bedrijfsbron voor project- en objectinformatie.', 'Losse e-mail, spreadsheets en berichten kunnen input zijn, maar worden geen parallelle waarheid naast Office.', 'Bij ieder proces waarin informatie structureel moet worden hergebruikt of gecontroleerd.', 'Leg informatie één keer op het juiste object vast en hergebruik die bron in processen en rapportages.'),
      'mjop-reserveringsprijs' => $this->item('MJOP-reserveringsprijs', 'Een onderhoudsreservering en geen aannemersofferte.', 'De reserveringsprijs houdt rekening met onzekerheid, toekomstige uitvoering en passende zekerheid rond onderhoudskosten.', 'Bij MJOP en langetermijnonderhoudsbegrotingen.', 'Houd kostprijs, verwachte aannemersprijs en MJOP-reserveringsprijs expliciet van elkaar gescheiden.'),
    ];
  }

  private function item(string $title, string $summary, string $meaning, string $use, string $rule): array {
    return compact('title', 'summary', 'meaning', 'use', 'rule');
  }
}
