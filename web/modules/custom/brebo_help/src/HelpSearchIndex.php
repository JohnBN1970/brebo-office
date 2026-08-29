<?php

declare(strict_types=1);

namespace Drupal\brebo_help;

use Drupal\Core\Url;

final class HelpSearchIndex {

  public static function items(): array {
    return [
      self::item('module', 'Gebouwen, woningen en productposities', 'Beheer de fysieke vastgoedstructuur als vaste bron voor projecten, inspecties, documenten en onderhoud.', ['gebouwen', 'woningen', 'productposities', 'gebouwmodel'], 'brebo_help.module', ['module' => 'gebouwen']),
      self::item('module', 'Projecten en projectcockpit', 'Stuur een opdracht vanuit één projectbeeld op scope, planning, geld, capaciteit, kwaliteit en besluiten.', ['project', 'projecten', 'projectcockpit', 'scope', 'planning'], 'brebo_help.module', ['module' => 'projecten']),
      self::item('module', 'Calculaties en offertes', 'Bouw een herleidbare commerciële prijs op van kostprijs tot aanbieding.', ['calculatie', 'offerte', 'kostprijs', 'marge', 'staartkosten'], 'brebo_help.module', ['module' => 'calculaties']),
      self::item('module', 'Taken en regieacties', 'Maak opvolging zichtbaar met eigenaar, context, deadline en aantoonbare afronding.', ['taken', 'taak', 'regieacties', 'actie', 'opvolging'], 'brebo_help.module', ['module' => 'taken']),
      self::item('module', 'Relaties, contactpersonen en CRM', 'Leg organisaties, personen, commerciële kansen en contacthistorie één keer centraal vast.', ['relaties', 'crm', 'contactpersoon', 'organisatie', 'kans'], 'brebo_help.module', ['module' => 'relaties']),
      self::item('module', 'Mail en communicatie', 'Koppel relevante communicatie aan de juiste bedrijfscontext zonder de mailbox als dossier te gebruiken.', ['mail', 'e-mail', 'communicatie', 'bijlage', 'koppelen'], 'brebo_help.module', ['module' => 'mail']),
      self::item('module', 'Finance en financiële controle', 'Maak verplichtingen, afwijkingen, cashflow en financiële beslissingen tijdig zichtbaar.', ['finance', 'financieel', 'verplichtingen', 'cashflow', 'werkbegroting'], 'brebo_help.module', ['module' => 'finance']),
      self::item('module', 'Kwaliteit, restpunten en oplevering', 'Borg bevinding, bewijs, herstel en nacontrole in één controleketen.', ['kwaliteit', 'restpunt', 'oplevering', 'gebrek', 'nacontrole'], 'brebo_help.module', ['module' => 'kwaliteit']),
      self::item('handleiding', 'Starten met BREBO Office', 'Overzicht van de vaste werkwijze in Office.', ['start', 'office', 'navigatie'], 'brebo_help.article', ['article' => 'starten-met-office']),
      self::item('rol', 'Projectleider: sturen vanuit de projectcockpit', 'Stuur scope, planning, geld, kwaliteit en besluiten vanuit één projectbeeld.', ['projectleider', 'projectcockpit', 'werkbegroting', 'planning'], 'brebo_help.article', ['article' => 'rol-projectleider']),
      self::item('rol', 'Werkvoorbereider: uitvoering gereedmaken', 'Maak informatie, materiaal, inkoop, planning en documenten uitvoeringsgereed.', ['werkvoorbereider', 'document', 'inkoop', 'planning'], 'brebo_help.article', ['article' => 'rol-werkvoorbereider']),
      self::item('rol', 'Uitvoerder: dagelijkse aansturing en controle', 'Stuur mensen en werkzaamheden aan en leg voortgang en afwijkingen vast.', ['uitvoerder', 'voortgang', 'planning', 'kwaliteit'], 'brebo_help.article', ['article' => 'rol-uitvoerder']),
      self::item('rol', 'Calculator/Inkoper: prijs en verplichtingen beheersen', 'Bewaak calculatie, inkoop, kostprijs, marge en projectverplichtingen.', ['calculator', 'inkoper', 'calculatie', 'inkoop', 'marge'], 'brebo_help.article', ['article' => 'rol-calculator-inkoper']),
      self::item('rol', 'KAM/Kwaliteit: onafhankelijk controleren', 'Borg veiligheid, kwaliteit, bewijs en oplevering onafhankelijk.', ['kam', 'kwaliteit', 'vca', 'oplevering', 'nacontrole'], 'brebo_help.article', ['article' => 'rol-kam-kwaliteit']),
      self::item('handleiding', 'Van calculatie naar offerte', 'Bouw prijs, scope en commerciële aanbieding gecontroleerd op.', ['calculatie', 'offerte', 'btw', 'g-rekening'], 'brebo_help.article', ['article' => 'calculatie-offerte']),
      self::item('handleiding', 'Een project starten', 'Start uitvoering vanuit de juiste project- en werkbegrotingsbasis.', ['project', 'werkbegroting', 'cockpit'], 'brebo_help.article', ['article' => 'project-starten']),
      self::item('handleiding', 'Een document aan het juiste object koppelen', 'Bewaar project- en gebouwdocumenten herleidbaar in Office.', ['document', 'upload', 'bestand', 'gebouw'], 'brebo_help.article', ['article' => 'document-koppelen']),
      self::item('handleiding', 'Personeel en diensten plannen', 'Plan beschikbare mensen en ploegen tegen de projectbehoefte.', ['personeel', 'planning', 'dienst', 'ploeg'], 'brebo_help.article', ['article' => 'personeel-plannen']),
      self::item('taak', 'Een project gereedmaken voor uitvoering', 'Controleer scope, gebouw, contract, werkbegroting, planning en verantwoordelijkheden.', ['project', 'vrijgeven', 'uitvoering', 'werkbegroting'], 'brebo_help.task', ['task' => 'project-vrijgeven']),
      self::item('taak', 'Een projectafwijking verwerken', 'Beoordeel een afwijking direct op scope, tijd, geld en kwaliteit.', ['afwijking', 'project', 'impact', 'besluit'], 'brebo_help.task', ['task' => 'afwijking-verwerken']),
      self::item('taak', 'Inkoop controleren vóór opdracht', 'Controleer een verplichting inhoudelijk en financieel vóór opdracht.', ['inkoop', 'opdracht', 'leverancier', 'budget'], 'brebo_help.task', ['task' => 'inkoop-controleren']),
      self::item('taak', 'Een restpunt aantoonbaar afronden', 'Van constatering naar herstel en onafhankelijke nacontrole.', ['restpunt', 'herstel', 'nacontrole', 'kwaliteit'], 'brebo_help.task', ['task' => 'restpunt-afronden']),
      self::item('taak', 'De werkdag op de bouw controleren', 'Maak dagelijkse projectsturing op afstand mogelijk.', ['werkdag', 'bouw', 'uren', 'voortgang'], 'brebo_help.task', ['task' => 'werkdag-controleren']),
      self::item('taak', 'Een financiële afwijking beslissen', 'Zet een financieel signaal om in een expliciet en opvolgbaar besluit.', ['financieel', 'afwijking', 'cashflow', 'besluit'], 'brebo_help.task', ['task' => 'financiele-afwijking']),
      self::item('begrip', 'Werkbegroting', 'De goedgekeurde operationele financiële basis van een project.', ['werkbegroting', 'budget', 'project', 'financieel'], 'brebo_help.term', ['term' => 'werkbegroting']),
      self::item('begrip', 'Stelpost', 'Een voorlopig bedrag voor werk waarvan omvang of keuze nog niet definitief vaststaat.', ['stelpost', 'offerte', 'verrekening'], 'brebo_help.term', ['term' => 'stelpost']),
      self::item('begrip', 'Verrekenpost', 'Een post die later op werkelijke hoeveelheid of afspraak wordt afgerekend.', ['verrekenpost', 'hoeveelheid', 'afrekening'], 'brebo_help.term', ['term' => 'verrekenpost']),
      self::item('begrip', 'BTW verlegd', 'Fiscale verleggingsregeling waarbij de BTW-heffing naar de afnemer verschuift.', ['btw', 'verlegd', 'fiscaal'], 'brebo_help.term', ['term' => 'btw-verlegd']),
      self::item('begrip', 'G-rekening', 'Geblokkeerde rekening voor relevante keten- of inlenersaansprakelijkheid.', ['g-rekening', 'betaling', 'onderaannemer'], 'brebo_help.term', ['term' => 'g-rekening']),
      self::item('begrip', 'MJOP-reserveringsprijs', 'Een onderhoudsreservering en geen aannemersofferte.', ['mjop', 'reserveringsprijs', 'onderhoud'], 'brebo_help.term', ['term' => 'mjop-reserveringsprijs']),
      self::item('vraag', 'Waarom kan ik dit niet goedkeuren?', 'Controleer verplichte voorwaarden, rol en eerdere procesfase.', ['goedkeuren', 'blokkade', 'fasepoort', 'rechten'], 'brebo_help.faq_item', ['faq' => 'kan-niet-goedkeuren']),
      self::item('vraag', 'Waar is mijn document gebleven?', 'Controleer de broncontext, koppeling, documenttype en rechten.', ['document', 'bestand', 'vinden'], 'brebo_help.faq_item', ['faq' => 'document-niet-vindbaar']),
      self::item('vraag', 'Wat doe ik bij “Er ging onverwacht iets fout”?', 'Leg scherm, handeling, tijdstip en request-ID vast en controleer dubbele verwerking.', ['foutmelding', 'error', 'request-id', 'onverwacht'], 'brebo_help.faq_item', ['faq' => 'foutmelding']),
      self::item('vraag', 'Wat doe ik als inkoop boven de werkbegroting uitkomt?', 'Maak de budgetafwijking zichtbaar en laat deze vóór opdracht besluiten.', ['inkoop', 'budget', 'werkbegroting', 'overschrijding'], 'brebo_help.faq_item', ['faq' => 'inkoop-boven-budget']),
      self::item('vraag', 'Waarom mag een restpunt nog niet dicht?', 'Herstel, bewijs en nacontrole moeten voldoende zijn.', ['restpunt', 'sluiten', 'nacontrole'], 'brebo_help.faq_item', ['faq' => 'restpunt-niet-sluiten']),
      self::item('vraag', 'Mijn uren of klokregistratie kloppen niet. Wat doe ik?', 'Corrigeer uren gecontroleerd en behoud herleidbaarheid.', ['uren', 'klokken', 'correctie'], 'brebo_help.faq_item', ['faq' => 'uren-kloppen-niet']),
    ];
  }

  public static function search(string $query): array {
    $needle = mb_strtolower(trim($query));
    if ($needle === '') {
      return [];
    }
    return array_values(array_filter(self::items(), static fn(array $item): bool => str_contains($item['search'], $needle)));
  }

  private static function item(string $type, string $title, string $summary, array $keywords, string $route, array $parameters): array {
    return [
      'type' => $type,
      'title' => $title,
      'summary' => $summary,
      'search' => mb_strtolower(implode(' ', [$title, $summary, implode(' ', $keywords)])),
      'url' => Url::fromRoute($route, $parameters),
    ];
  }
}
