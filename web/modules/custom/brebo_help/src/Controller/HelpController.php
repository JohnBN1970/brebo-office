<?php

declare(strict_types=1);

namespace Drupal\brebo_help\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HelpController extends ControllerBase {

  public function __construct(private readonly RequestStack $requestStack) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('request_stack'));
  }

  public function center(): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = trim((string) $request?->query->get('q', ''));
    $context = trim((string) $request?->query->get('context', ''));
    $articles = $this->articles();
    if ($query !== '') {
      $needle = mb_strtolower($query);
      $articles = array_filter($articles, static function (array $article) use ($needle): bool {
        $haystack = mb_strtolower(implode(' ', [$article['title'], $article['category'], $article['summary'], implode(' ', $article['keywords'])]));
        return str_contains($haystack, $needle);
      });
    }
    if ($context !== '') {
      uasort($articles, static fn(array $a, array $b): int => (int) str_contains($b['context'], $context) <=> (int) str_contains($a['context'], $context));
    }
    $grouped = [];
    foreach ($articles as $id => $article) {
      $grouped[$article['category']][$id] = $article;
    }
    $build = ['#attached' => ['library' => ['brebo_help/help']], 'content' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-help']], 'intro' => ['#markup' => '<p class="brebo-help__intro">Vind uitleg over schermen, BREBO-processen, rollen en dagelijkse taken. Zoek op een handeling, begrip, rol of onderdeel van Office.</p>'], 'search' => ['#type' => 'inline_template', '#template' => '<form class="brebo-help__search" method="get"><label class="visually-hidden" for="brebo-help-search">Zoeken in Help</label><input id="brebo-help-search" name="q" type="search" value="{{ query }}" placeholder="Bijv. projectleider, offerte, uitklokken"><button type="submit">Zoeken</button></form>', '#context' => ['query' => $query]]]];
    if ($grouped === []) {
      $build['content']['empty'] = ['#markup' => '<p class="brebo-help__empty">Geen helpresultaten gevonden. Probeer een andere zoekterm.</p>'];
      return $build;
    }
    foreach ($grouped as $category => $items) {
      $cards = '';
      foreach ($items as $id => $article) {
        $url = Url::fromRoute('brebo_help.article', ['article' => $id]);
        $cards .= '<article class="brebo-help__card"><h3>' . Link::fromTextAndUrl($article['title'], $url)->toString() . '</h3><p>' . $article['summary'] . '</p><div class="brebo-help__meta">' . $article['type'] . '</div></article>';
      }
      $build['content']['category_' . md5($category)] = ['#markup' => '<section><h2>' . $category . '</h2><div class="brebo-help__grid">' . $cards . '</div></section>'];
    }
    return $build;
  }

  public function article(string $article): array {
    $articles = $this->articles();
    if (!isset($articles[$article])) { throw new NotFoundHttpException(); }
    $item = $articles[$article];
    $steps = '';
    foreach ($item['steps'] as $step) { $steps .= '<li>' . $step . '</li>'; }
    return ['#attached' => ['library' => ['brebo_help/help']], '#markup' => '<article class="brebo-help"><p class="brebo-help__meta">' . $item['category'] . ' · ' . $item['type'] . '</p><p class="brebo-help__intro">' . $item['summary'] . '</p><h2>Zo werkt het</h2><ol class="brebo-help__steps">' . $steps . '</ol><h2>Waarom BREBO dit zo doet</h2><p>' . $item['why'] . '</p><p class="brebo-help__back">' . Link::fromTextAndUrl('← Terug naar Help', Url::fromRoute('brebo_help.center'))->toString() . '</p></article>'];
  }

  public function articleTitle(string $article): string {
    $articles = $this->articles();
    if (!isset($articles[$article])) { throw new NotFoundHttpException(); }
    return $articles[$article]['title'];
  }

  private function articles(): array {
    return [
      'starten-met-office' => $this->item('Aan de slag', 'Starten met BREBO Office', 'Overzicht van de vaste werkwijze in Office.', ['Open het relevante onderdeel vanuit de hoofdnavigatie.', 'Werk altijd vanuit het juiste object: relatie, gebouw, kans of project.', 'Leg besluiten en wijzigingen vast op het object waar ze bij horen.', 'Gebruik de gekoppelde documenten, communicatie en controles in plaats van losse registraties.'], 'Office is de centrale bron. Informatie hoort één keer op de juiste plaats te worden vastgelegd en daarna te worden hergebruikt.', 'Handleiding', 'office dashboard navigatie', ['start', 'office', 'navigatie']),
      'rol-projectleider' => $this->item('Werken per rol', 'Projectleider: sturen vanuit de projectcockpit', 'Houd scope, planning, geld, kwaliteit en besluiten vanuit één projectbeeld bestuurbaar.', ['Begin bij de projectcockpit en controleer actuele status, verantwoordelijken en open signalen.', 'Controleer de goedgekeurde werkbegroting en beoordeel afwijkingen voordat verplichtingen ontstaan.', 'Bewaak planning, personeelsinzet, inkoop, communicatie en kwaliteit vanuit de gekoppelde projectobjecten.', 'Leg besluiten, wijzigingen en escalaties vast op het project in plaats van in losse notities.', 'Sluit open acties pas wanneer uitvoering én bewijs zijn gecontroleerd.'], 'De projectleider moet kunnen sturen op feiten uit Office. De cockpit brengt de bestaande bronnen samen zonder een tweede projectadministratie te maken.', 'Rolhandleiding', 'project cockpit budget planning finance quality', ['projectleider', 'projectleiding', 'cockpit', 'sturen']),
      'rol-werkvoorbereider' => $this->item('Werken per rol', 'Werkvoorbereider: uitvoering gereedmaken', 'Zorg dat informatie, materiaal, inkoop, planning en documenten vóór uitvoering compleet en herleidbaar zijn.', ['Controleer scope, gebouwgegevens, tekeningen en projectdocumenten op volledigheid.', 'Vertaal de werkbegroting en projectplanning naar concrete voorbereidings- en inkoopbehoefte.', 'Zet prijsaanvragen, leveranciersinformatie en benodigde documenten op het juiste project.', 'Controleer raakvlakken, levertijden, uitvoeringsvoorwaarden en ontbrekende informatie vóór vrijgave.', 'Leg wijzigingen direct vast zodat Uitvoerder, Projectleider en Calculator/Inkoper vanuit dezelfde bron werken.'], 'Goede werkvoorbereiding voorkomt stilstand, improvisatie en faalkosten op de bouw. Daarom blijft alle voorbereiding gekoppeld aan dezelfde projectbron.', 'Rolhandleiding', 'project document planning procurement building', ['werkvoorbereider', 'werkvoorbereiding', 'inkoop', 'documenten']),
      'rol-uitvoerder' => $this->item('Werken per rol', 'Uitvoerder: dagelijkse aansturing en controle', 'Stuur mensen en werkzaamheden dagelijks aan en leg voortgang, afwijkingen en kwaliteit direct vast.', ['Controleer vóór de werkdag planning, diensten, personeelsinzet en uit te voeren activiteiten.', 'Controleer op locatie of voorwaarden, materialen en informatie aanwezig zijn.', 'Leg voortgang, afwijkingen, restpunten en relevante foto’s vast bij het juiste projectonderdeel.', 'Escaleren zodra planning, kwaliteit, veiligheid of scope afwijkt; wacht niet tot de eindcontrole.', 'Controleer herstel en gereedmelding voordat een punt als afgerond wordt beschouwd.'], 'Dagelijkse digitale vastlegging maakt projectleiding op afstand mogelijk en voorkomt dat afwijkingen pas bij oplevering zichtbaar worden.', 'Rolhandleiding', 'planning inzet clock quality project', ['uitvoerder', 'uitvoering', 'dagelijkse aansturing', 'voortgang']),
      'rol-calculator-inkoper' => $this->item('Werken per rol', 'Calculator/Inkoper: prijs en verplichtingen beheersen', 'Bewaak vanaf calculatie tot inkoop dat scope, kostprijs, marge en verplichtingen aantoonbaar blijven.', ['Controleer calculatiebasis, hoeveelheden, uren, inkoopregels, opslagen en staartkosten.', 'Leg prijsaanvragen en leveranciersoffertes bij de juiste calculatie- of projectcontext vast.', 'Vergelijk aanbiedingen inhoudelijk op scope, voorwaarden, uitsluitingen en levertijd; niet alleen op totaalprijs.', 'Controleer de goedgekeurde werkbegroting voordat een projectverplichting wordt aangegaan.', 'Signaleer prijs-, scope- en budgetafwijkingen direct aan Projectleider en financiële controle.'], 'BREBO wil financiële afwijkingen zien vóór de factuur komt. Calculatie, inkoop en werkbegroting vormen daarom één controleketen.', 'Rolhandleiding', 'calculation procurement supplier budget finance', ['calculator', 'inkoper', 'inkoop', 'calculatie', 'marge']),
      'rol-kam-kwaliteit' => $this->item('Werken per rol', 'KAM/Kwaliteit: onafhankelijk controleren', 'Borg veiligheid, kwaliteit, bewijs en oplevering onafhankelijk van de uitvoeringsdruk.', ['Controleer vooraf de relevante kwaliteits-, veiligheids- en projectspecifieke eisen.', 'Leg inspecties, afwijkingen en gebreken objectief vast met locatie, oorzaak, gevolg, urgentie en bewijs.', 'Keur een onderdeel niet goed zolang aantoonbaar herstel of vereiste documentatie ontbreekt.', 'Controleer herstel onafhankelijk en sluit het punt pas na nacontrole.', 'Bewaak opleverdocumenten, garanties en terugkerende afwijkingen zodat structurele oorzaken zichtbaar worden.'], 'Kwaliteitsborging is een zelfstandige controlefunctie. Ook wanneer uitvoering door wil, moet aantoonbare kwaliteit leidend blijven om klachten, herstelwerk en garantieschade te voorkomen.', 'Rolhandleiding', 'quality defect verification project document', ['kam', 'kwaliteitsmanager', 'kwaliteit', 'vca', 'oplevering']),
      'relatie-aanmaken' => $this->item('Relaties & CRM', 'Een relatie of contactpersoon vastleggen', 'Leg organisaties en contactpersonen één keer centraal vast.', ['Controleer eerst of de relatie al bestaat.', 'Maak of open de organisatie.', 'Leg de relevante contactpersonen en contactgegevens vast.', 'Koppel de relatie daarna aan kans, offerte, gebouw of project.'], 'Een centrale relatie voorkomt dubbele klantgegevens en maakt communicatie en projecthistorie herleidbaar.', 'Taak', 'relation contact crm', ['relatie', 'contactpersoon', 'klant']),
      'kans-opvolgen' => $this->item('Relaties & CRM', 'Een commerciële kans opvolgen', 'Gebruik de funnel voor commerciële opvolging tot opdracht of afsluiting.', ['Maak de kans bij de juiste relatie aan.', 'Leg status, verantwoordelijke en eerstvolgende actie vast.', 'Registreer contactmomenten op de kans.', 'Koppel calculatie/offerte wanneer die fase start.', 'Sluit de kans af met de juiste uitkomst.'], 'BREBO houdt commerciële opvolging en projectuitvoering gescheiden, maar wel volledig aan elkaar gekoppeld.', 'Proces', 'funnel opportunity calculation', ['kans', 'funnel', 'lead']),
      'calculatie-offerte' => $this->item('Calculatie & Offerte', 'Van calculatie naar offerte', 'Bouw prijs, scope en commerciële aanbieding gecontroleerd op.', ['Start vanuit de juiste kans of relatie.', 'Vul calculatieregels, uren, inkoop, opslagen en staartkosten in.', 'Controleer stelposten, verrekenposten, opties, BTW en eventuele G-rekening.', 'Maak een offerteversie en controleer de voorbeeldweergave.', 'Leg goedkeuring en verzending vast.'], 'Calculatie en offerte vormen samen één beheerst commercieel proces, terwijl een offerte ook zonder voorafgaande calculatie kan bestaan.', 'Proces', 'calculation quotation offer', ['calculatie', 'offerte', 'btw', 'g-rekening']),
      'project-starten' => $this->item('Projecten', 'Een project starten', 'Start uitvoering pas wanneer commerciële en operationele basis voldoende is vastgelegd.', ['Open de gewonnen kans of opdracht.', 'Controleer opdrachtgever, gebouw, scope en contractgegevens.', 'Controleer de goedgekeurde werkbegroting.', 'Leg verantwoordelijken en projectstatus vast.', 'Open daarna de projectcockpit voor uitvoering en bewaking.'], 'De goedgekeurde werkbegroting is de operationele financiële basis voor projectcontrole.', 'Proces', 'project cockpit budget', ['project', 'werkbegroting', 'cockpit']),
      'document-koppelen' => $this->item('Documenten', 'Een document aan het juiste object koppelen', 'Bewaar project- en gebouwdocumenten herleidbaar in Office.', ['Open het object waarop het document betrekking heeft.', 'Upload of selecteer het document.', 'Kies het juiste documenttype en leg relevante metadata vast.', 'Controleer de koppeling met project, gebouw, relatie of ander bronobject.'], 'Documenten moeten vindbaar blijven vanuit de bedrijfscontext en niet alleen vanuit een losse bestandsmap.', 'Taak', 'document project building', ['document', 'upload', 'bestand']),
      'personeel-plannen' => $this->item('Planning & Personeel', 'Personeel en diensten plannen', 'Plan beschikbare mensen en ploegen tegen de projectbehoefte.', ['Controleer de projectplanning en benodigde capaciteit.', 'Controleer beschikbaarheid en ploegindeling.', 'Maak de dienst of personeelsinzet.', 'Koppel deze aan project en geplande activiteit.', 'Controleer conflicten voordat de inzet definitief wordt.'], 'Personeelsplanning moet rechtstreeks aansluiten op projectplanning en werkbegroting zodat capaciteit en kosten bestuurbaar blijven.', 'Taak', 'planning inzet dienst availability', ['personeel', 'planning', 'dienst', 'ploeg']),
      'in-uitklokken' => $this->item('Planning & Personeel', 'In- en uitklokken', 'Registreer gewerkte tijd op de juiste dienst en het juiste project.', ['Open de mobiele klokregistratie.', 'Controleer project en dienst voordat je inklokt.', 'Klok in bij aanvang van het werk.', 'Klok uit zodra de werkzaamheden eindigen.', 'Meld een foutieve registratie voor correctie in plaats van een tweede registratie te improviseren.'], 'Betrouwbare klokregistratie voedt urencontrole, projectbewaking en financiële nacalculatie.', 'Taak', 'clock mobile hours inzet', ['inklokken', 'uitklokken', 'uren']),
      'inkoop-registreren' => $this->item('Inkoop & Financieel', 'Inkoop en verplichtingen registreren', 'Koppel inkoop aan project, leverancier en financiële basis.', ['Selecteer de juiste leverancier.', 'Koppel de inkoop aan het juiste project of gebouw.', 'Controleer scope, bedrag, BTW en betaalafspraken.', 'Leg de relatie met werkbegroting of inkooppositie vast.', 'Volg afwijkingen en goedkeuringen via de financiële controle.'], 'BREBO wil verplichtingen kennen vóórdat alleen de uiteindelijke factuur zichtbaar wordt.', 'Proces', 'finance procurement supplier', ['inkoop', 'leverancier', 'factuur']),
      'financiele-beslissing' => $this->item('Inkoop & Financieel', 'Financiële beslissingen en afwijkingen', 'Behandel financiële fasepoortbesluiten en escalaties expliciet.', ['Open Finance en de financiële beslissingen.', 'Controleer de bron, het project en de afwijking.', 'Beoordeel impact op budget, cashflow en resultaat.', 'Leg besluit, verantwoordelijke en onderbouwing vast.', 'Volg open acties tot afsluiting.'], 'Financiële controle moet aantoonbaar zijn en niet afhankelijk van losse kennis of mondelinge afspraken.', 'Proces', 'finance decision escalation', ['finance', 'beslissing', 'afwijking']),
      'restpunt-vastleggen' => $this->item('Kwaliteit & Oplevering', 'Een gebrek of restpunt vastleggen', 'Leg bevindingen controleerbaar vast en volg herstel tot akkoord.', ['Koppel het punt aan project, gebouw en zo mogelijk locatie/onderdeel.', 'Beschrijf gebrek, oorzaak, gevolg en urgentie.', 'Voeg duidelijke foto’s of bewijs toe.', 'Wijs herstel en termijn toe.', 'Controleer herstel en sluit pas daarna het punt.'], 'BREBO stuurt op het voorkomen van klachten, herstelwerk en garantieschade; bewijs en nacontrole horen daarom bij dezelfde registratie.', 'Taak', 'quality defect snag resident', ['restpunt', 'gebrek', 'kwaliteit', 'oplevering']),
      'mail-koppelen' => $this->item('Mail & Communicatie', 'E-mail aan de juiste context koppelen', 'Maak projectcommunicatie terugvindbaar zonder de mailbox als dossier te gebruiken.', ['Open het relevante bericht.', 'Controleer afzender, ontvangers en inhoud.', 'Koppel het bericht aan de juiste relatie, kans, project of ander object.', 'Controleer bijlagen en sla relevante documenten in de documentcontext op.'], 'Communicatie krijgt waarde wanneer zij onderdeel is van het project- of relatiedossier en later herleidbaar blijft.', 'Taak', 'mail intake project relation', ['mail', 'e-mail', 'koppelen']),
      'klantportaal' => $this->item('Klantportaal', 'Projectinformatie extern delen', 'Alleen expliciet vrijgegeven Office-informatie mag extern zichtbaar worden.', ['Controleer of het klantportaal voor het project actief is.', 'Controleer welke contactpersonen toegang hebben.', 'Geef alleen bedoelde projectinformatie expliciet vrij.', 'Controleer toegang en blokkades bij wijzigingen in contactpersonen.', 'Houd Office als bron; kopieer geen aparte portaaladministratie.'], 'Externe toegang is een gecontroleerde projectweergave van Office en nooit een tweede bron van waarheid.', 'Proces', 'client portal project external', ['klantportaal', 'extern', 'toegang']),
      'glasmodule' => $this->item('Specialistische modules', 'Werken met de glasmodule', 'Leg glasmaten en glasadvies projectgericht vast.', ['Open Glas vanuit de juiste projectcontext.', 'Voer de benodigde maten en eigenschappen in.', 'Controleer de maatregels en technische controles.', 'Beoordeel het voorgestelde glastype en de berekeningsuitgangspunten.', 'Bewaar de uitkomst bij het project.'], 'Technische glasgegevens moeten reproduceerbaar en gekoppeld aan de projectbron blijven.', 'Handleiding', 'glass project dimensions', ['glas', 'glasmaten', 'windbelasting']),
      'probleem-oplossen' => $this->item('Problemen oplossen', 'Als BREBO Office een foutmelding geeft', 'Verzamel eerst context zodat een fout reproduceerbaar en veilig opgelost kan worden.', ['Noteer wat je probeerde te doen en op welk object.', 'Noteer het tijdstip en eventuele request-ID of zichtbare foutcode.', 'Probeer niet herhaaldelijk dezelfde mutatie als onduidelijk is of deze al is verwerkt.', 'Controleer na herstel altijd de functionele uitkomst en niet alleen of de pagina weer opent.'], 'Een reproduceerbare fout met object- en requestcontext is sneller en veiliger op te lossen dan een algemene melding.', 'Probleemoplossing', 'error exception request route', ['fout', 'error', 'request-id']),
    ];
  }

  private function item(string $category, string $title, string $summary, array $steps, string $why, string $type, string $context, array $keywords): array {
    return compact('category', 'title', 'summary', 'steps', 'why', 'type', 'context', 'keywords');
  }
}
