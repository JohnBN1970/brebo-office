<?php

declare(strict_types=1);

namespace Drupal\brebo_help\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FaqController extends ControllerBase {

  public function __construct(private readonly RequestStack $requestStack) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('request_stack'));
  }

  public function index(): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = trim((string) $request?->query->get('q', ''));
    $items = $this->items();
    if ($query !== '') {
      $needle = mb_strtolower($query);
      $items = array_filter($items, static function (array $item) use ($needle): bool {
        return str_contains(mb_strtolower(implode(' ', [$item['question'], $item['answer'], $item['category'], implode(' ', $item['keywords'])])), $needle);
      });
    }

    $cards = '';
    foreach ($items as $id => $item) {
      $cards .= '<article class="brebo-help__card"><p class="brebo-help__meta">' . $item['category'] . '</p><h3>' . Link::fromTextAndUrl($item['question'], Url::fromRoute('brebo_help.faq_item', ['faq' => $id]))->toString() . '</h3><p>' . $item['answer'] . '</p></article>';
    }

    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<section class="brebo-help"><p class="brebo-help__intro">Veelvoorkomende vragen en storingen in BREBO Office. Zoek op wat je ziet of wat je probeerde te doen.</p><form class="brebo-help__search" method="get"><input name="q" type="search" value="' . htmlspecialchars($query, ENT_QUOTES) . '" placeholder="Bijv. goedkeuren, document, foutmelding"><button type="submit">Zoeken</button></form><div class="brebo-help__grid">' . ($cards !== '' ? $cards : '<p class="brebo-help__empty">Geen passende vraag gevonden.</p>') . '</div></section>',
    ];
  }

  public function item(string $faq): array {
    $items = $this->items();
    if (!isset($items[$faq])) {
      throw new NotFoundHttpException();
    }
    $item = $items[$faq];
    $steps = '';
    foreach ($item['steps'] as $step) {
      $steps .= '<li>' . $step . '</li>';
    }
    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<article class="brebo-help"><p class="brebo-help__meta">' . $item['category'] . '</p><p class="brebo-help__intro">' . $item['answer'] . '</p><h2>Controleer dit</h2><ol class="brebo-help__steps">' . $steps . '</ol><h2>Als het probleem blijft bestaan</h2><p>' . $item['escalation'] . '</p><p class="brebo-help__back">' . Link::fromTextAndUrl('← Terug naar veelgestelde vragen', Url::fromRoute('brebo_help.faq'))->toString() . '</p></article>',
    ];
  }

  public function title(string $faq): string {
    $items = $this->items();
    if (!isset($items[$faq])) {
      throw new NotFoundHttpException();
    }
    return $items[$faq]['question'];
  }

  private function items(): array {
    return [
      'kan-niet-goedkeuren' => $this->faq('Goedkeuringen', 'Waarom kan ik dit niet goedkeuren?', 'Een goedkeuring wordt meestal geblokkeerd omdat een verplichte voorwaarde, rol of eerdere fase nog niet compleet is.', ['Controleer status en fase van het object.', 'Controleer of verplichte velden, documenten en financiële basis compleet zijn.', 'Controleer of jouw rol de betreffende goedkeuring mag uitvoeren.', 'Controleer open afwijkingen of blokkades die eerst moeten worden opgelost.'], 'Leg vast wat ontbreekt en escaleer naar de verantwoordelijke rol; omzeil een fasepoort niet met een losse afspraak.', ['goedkeuren', 'blokkade', 'fasepoort']),
      'document-niet-vindbaar' => $this->faq('Documenten', 'Waar is mijn document gebleven?', 'Documenten horen terugvindbaar te zijn vanuit het object waaraan ze zijn gekoppeld.', ['Controleer project, gebouw, relatie of andere broncontext.', 'Zoek op documenttype, naam en datum.', 'Controleer of het document wel aan het juiste object is gekoppeld.', 'Controleer of je rechten hebt op het document of de betreffende projectcontext.'], 'Maak geen tweede kopie als je niet zeker weet waar het origineel staat; herstel eerst de koppeling of vindbaarheid.', ['document', 'bestand', 'vinden']),
      'foutmelding' => $this->faq('Technische fout', 'Wat doe ik bij “Er ging onverwacht iets fout”?', 'Stop na de eerste duidelijke foutmelding met herhaald klikken als niet zeker is of de mutatie al is uitgevoerd.', ['Noteer tijdstip, scherm en handeling.', 'Controleer na verversen of de mutatie toch al is verwerkt.', 'Noteer request-ID of foutcode wanneer die zichtbaar is.', 'Controleer het betrokken object op dubbele of gedeeltelijke verwerking.'], 'Meld scherm, object, tijdstip en request-ID aan beheer. Die context maakt de fout reproduceerbaar zonder te gokken.', ['foutmelding', 'error', 'request-id']),
      'wijziging-niet-zichtbaar' => $this->faq('Weergave', 'Waarom zie ik mijn wijziging niet?', 'Een wijziging kan buiten jouw huidige filter, status, versie of context vallen.', ['Controleer of opslaan daadwerkelijk is bevestigd.', 'Controleer filters, tabs, status en geselecteerd project/gebouw.', 'Controleer of je naar de actuele versie kijkt.', 'Ververs pas daarna de pagina en controleer opnieuw.'], 'Als de bronwaarde correct is maar de weergave achterblijft, meld dan bronobject, scherm en verwachte waarde.', ['wijziging', 'niet zichtbaar', 'filter']),
      'verkeerde-projectcontext' => $this->faq('Projecten', 'Ik zie de verkeerde projectinformatie. Wat nu?', 'Controleer eerst of je werkelijk in het juiste project, gebouw en object werkt voordat je gegevens aanpast.', ['Controleer projectnummer en opdrachtgever.', 'Controleer gekoppeld gebouw en eventuele woning/positie.', 'Controleer of het geopende document, calculatie of taak bij dit project hoort.', 'Verplaats of wijzig niets zolang de broncontext niet vaststaat.'], 'Escaleren bij een foutieve koppeling is veiliger dan gegevens opnieuw invoeren; Office moet één bron blijven.', ['project', 'verkeerde context', 'gebouw']),
      'inkoop-boven-budget' => $this->faq('Financieel', 'Wat doe ik als inkoop boven de werkbegroting uitkomt?', 'Een overschrijding is een afwijking die vóór opdrachtverlening zichtbaar en besloten moet zijn.', ['Vergelijk scope en hoeveelheid met de goedgekeurde werkbegroting.', 'Controleer of de aanbieding inhoudelijk gelijkwaardig is.', 'Leg oorzaak en financiële impact van de afwijking vast.', 'Laat de bevoegde rol besluiten vóórdat de verplichting wordt aangegaan.'], 'Niet achteraf “wegboeken”. De afwijking moet aantoonbaar zijn zodat projectresultaat en besluitvorming zuiver blijven.', ['inkoop', 'budget', 'overschrijding']),
      'restpunt-niet-sluiten' => $this->faq('Kwaliteit', 'Waarom mag een restpunt nog niet dicht?', 'Een restpunt is pas gereed als herstel én bewijs/nacontrole voldoende zijn.', ['Controleer of herstel daadwerkelijk is uitgevoerd.', 'Controleer foto, document of ander bewijs.', 'Controleer of de juiste persoon de nacontrole heeft gedaan.', 'Controleer of gevolgpunten of gekoppelde afwijkingen nog openstaan.'], 'Sluit nooit administratief omdat het werk “waarschijnlijk klaar” is; aantoonbare kwaliteit is leidend.', ['restpunt', 'sluiten', 'nacontrole']),
      'uren-kloppen-niet' => $this->faq('Uren & planning', 'Mijn uren of klokregistratie kloppen niet. Wat doe ik?', 'Corrigeer een foutieve registratie gecontroleerd; maak niet zomaar extra klokregels aan.', ['Controleer dienst, project en datum.', 'Controleer in- en uitkloktijd en eventuele pauze.', 'Controleer of een tweede registratie al bestaat.', 'Laat de bevoegde rol de afwijking corrigeren met behoud van herleidbaarheid.'], 'Uren voeden projectkosten en nacalculatie. Correcties moeten daarom traceerbaar blijven.', ['uren', 'klokken', 'correctie']),
      'geen-toegang' => $this->faq('Rechten', 'Waarom zie ik een onderdeel of knop niet?', 'BREBO Office toont functies op basis van rol, rechten, objectstatus en soms projectcontext.', ['Controleer of je in de juiste omgeving en op het juiste object zit.', 'Controleer of jouw rol deze handeling behoort te kunnen uitvoeren.', 'Controleer of de objectstatus de actie toestaat.', 'Laat beheer alleen rechten aanpassen als de bedrijfsrol dat echt vereist.'], 'Rechten worden niet verruimd om één processtap te omzeilen; de rolverdeling blijft leidend.', ['rechten', 'toegang', 'knop']),
    ];
  }

  private function faq(string $category, string $question, string $answer, array $steps, string $escalation, array $keywords): array {
    return compact('category', 'question', 'answer', 'steps', 'escalation', 'keywords');
  }
}
