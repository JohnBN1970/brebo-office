<?php

declare(strict_types=1);

namespace Drupal\brebo_help\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ModuleGuideController extends ControllerBase {

  public function index(): array {
    $cards = '';
    foreach ($this->guides() as $id => $guide) {
      $cards .= '<article class="brebo-help__card"><p class="brebo-help__meta">Modulehandleiding</p><h3>' . Link::fromTextAndUrl($guide['title'], Url::fromRoute('brebo_help.module', ['module' => $id]))->toString() . '</h3><p>' . $guide['purpose'] . '</p></article>';
    }

    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<section class="brebo-help"><p class="brebo-help__intro">Handleidingen voor de hoofdonderdelen van BREBO Office. Iedere handleiding volgt dezelfde vaste opbouw: doel, gebruik, rollen, controles, BREBO-regels en veelgemaakte fouten.</p><div class="brebo-help__grid">' . $cards . '</div></section>',
    ];
  }

  public function guide(string $module): array {
    $guides = $this->guides();
    if (!isset($guides[$module])) {
      throw new NotFoundHttpException();
    }
    $guide = $guides[$module];

    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<article class="brebo-help">'
        . '<p class="brebo-help__meta">Modulehandleiding</p>'
        . '<p class="brebo-help__intro">' . $guide['purpose'] . '</p>'
        . $this->section('Wanneer gebruik je dit?', $guide['use'])
        . $this->section('Wie werkt hiermee?', $guide['roles'])
        . $this->section('Vaste controles', $guide['checks'])
        . $this->section('BREBO-regels', $guide['rules'])
        . $this->section('Veelgemaakte fouten', $guide['mistakes'])
        . $this->section('Gerelateerde onderwerpen', $guide['related'])
        . '<p class="brebo-help__back">' . Link::fromTextAndUrl('← Terug naar modulehandleidingen', Url::fromRoute('brebo_help.modules'))->toString() . '</p>'
        . '</article>',
    ];
  }

  public function title(string $module): string {
    $guides = $this->guides();
    if (!isset($guides[$module])) {
      throw new NotFoundHttpException();
    }
    return $guides[$module]['title'];
  }

  private function section(string $title, array $items): string {
    $list = '';
    foreach ($items as $item) {
      $list .= '<li>' . $item . '</li>';
    }
    return '<h2>' . $title . '</h2><ul class="brebo-help__steps">' . $list . '</ul>';
  }

  private function guides(): array {
    return [
      'gebouwen' => [
        'title' => 'Gebouwen, woningen en productposities',
        'purpose' => 'Beheer de fysieke vastgoedstructuur als vaste bron voor projecten, inspecties, documenten, onderhoud en technische gegevens.',
        'use' => ['Gebruik Gebouwen zodra informatie blijvend bij het object hoort en niet alleen bij één project.', 'Gebruik Woningen voor zelfstandige eenheden binnen een gebouw.', 'Gebruik Productposities voor herleidbare bouwdelen, installaties of andere onderhouds- en uitvoeringsposities.'],
        'roles' => ['Werkvoorbereiding en Projectleiding voor object- en projectcontext.', 'KAM/Kwaliteit voor inspecties, gebreken en bewijs.', 'Onderhoud/MJOP voor langjarige objectinformatie.'],
        'checks' => ['Controleer eerst of gebouw, woning of positie al bestaat.', 'Gebruik de juiste hiërarchie: gebouw → woning/onderdeel → relevante projectcontext.', 'Controleer adres, objectidentiteit en koppelingen voordat nieuwe informatie wordt toegevoegd.', 'Koppel documenten en bevindingen aan het laagste zinvolle objectniveau.'],
        'rules' => ['Het gebouwmodel is de centrale bron; tekeningen en CAD zijn output, niet de primaire waarheid.', 'Maak geen tweede gebouwregistratie in een project als hetzelfde object al bestaat.', 'Historische objectinformatie blijft bij het gebouw beschikbaar, ook nadat een project sluit.'],
        'mistakes' => ['Hetzelfde gebouw dubbel aanmaken met een iets andere naam.', 'Projectinformatie gebruiken als vervanging voor structurele gebouwdata.', 'Documenten alleen uploaden zonder objectkoppeling.'],
        'related' => ['Documenten koppelen', 'Project starten', 'Restpunten en kwaliteit', 'MJOP en onderhoud'],
      ],
      'projecten' => [
        'title' => 'Projecten en projectcockpit',
        'purpose' => 'Stuur een opdracht vanuit één projectbeeld op scope, planning, geld, capaciteit, kwaliteit, communicatie en besluiten.',
        'use' => ['Gebruik een project zodra een opdracht uitvoeringsgericht wordt bestuurd.', 'Gebruik de projectcockpit als dagelijkse managementingang voor de actuele projectstatus.', 'Gebruik gekoppelde objecten in plaats van losse projectlijstjes.'],
        'roles' => ['Projectleider is primair verantwoordelijk voor integrale projectsturing.', 'Werkvoorbereider maakt uitvoering gereed.', 'Uitvoerder stuurt dagelijkse uitvoering.', 'Calculator/Inkoper en KAM leveren hun eigen controleketen.'],
        'checks' => ['Controleer opdrachtgever, gebouw, scope en verantwoordelijken.', 'Controleer of de werkbegroting is goedgekeurd voordat financiële verplichtingen worden aangegaan.', 'Bewaken planning, inzet, inkoop, afwijkingen, documenten en kwaliteit.', 'Sluit acties alleen wanneer uitvoering én bewijs compleet zijn.'],
        'rules' => ['Office is de bron van waarheid voor projectstatus en besluiten.', 'Een afwijking wordt zichtbaar gemaakt vóórdat deze financieel of technisch wordt weggeschreven.', 'Besluiten horen op het project thuis en niet alleen in e-mail of chat.'],
        'mistakes' => ['Project starten zonder voldoende scope- of budgetbasis.', 'Mondelinge wijzigingen niet vastleggen.', 'Een open actie sluiten omdat het werk “waarschijnlijk” gereed is.'],
        'related' => ['Project vrijgeven', 'Werkbegroting', 'Afwijkingen verwerken', 'Dagelijkse bouwcontrole'],
      ],
      'calculaties' => [
        'title' => 'Calculaties en offertes',
        'purpose' => 'Bouw een herleidbare commerciële prijs op en beheer de stap van kostprijs naar aanbieding zonder financiële logica buiten Office te verplaatsen.',
        'use' => ['Gebruik Calculatie voor kostprijsopbouw, uren, materiaal, inkoop, opslagen en staartkosten.', 'Gebruik Offerte voor de commerciële aanbieding en versiehistorie.', 'Een offerte mag bestaan zonder voorafgaande calculatie wanneer dat bewust is gekozen.'],
        'roles' => ['Calculator/Inkoper is inhoudelijk eigenaar van prijsopbouw en inkoopbasis.', 'Projectleiding beoordeelt scope en uitvoerbaarheid.', 'Directie/Finance bewaakt marge en uitzonderingen waar vereist.'],
        'checks' => ['Controleer hoeveelheden, uren, inkoopprijzen en regelniveau-marges.', 'Controleer stelposten, verrekenposten, opties, BTW en G-rekening.', 'Controleer open/gesloten presentatie en offerteversie vóór verzending.', 'Leg afwijkende aannames en uitsluitingen expliciet vast.'],
        'rules' => ['Kostprijs en verkoopprijs blijven van elkaar te onderscheiden.', 'Marge mag niet ontstaan uit onverklaarde verborgen bedragen.', 'Offerteversies blijven herleidbaar: wie, wanneer en wat gewijzigd is.'],
        'mistakes' => ['Alleen op totaalprijs controleren.', 'Stelpost en verrekenpost door elkaar gebruiken.', 'Een gewijzigde offerte overschrijven zonder versiehistorie.'],
        'related' => ['Van calculatie naar offerte', 'Stelpost', 'Verrekenpost', 'BTW verlegd', 'G-rekening'],
      ],
      'taken' => [
        'title' => 'Taken en regieacties',
        'purpose' => 'Maak opvolging zichtbaar door acties te koppelen aan het object en proces waarop ze betrekking hebben.',
        'use' => ['Gebruik Taken voor concrete opvolging met eigenaar en status.', 'Gebruik een taak niet als vervanging voor een projectbesluit, gebrek, document of financieel object.', 'Maak taken vanuit de juiste project- of objectcontext.'],
        'roles' => ['Iedere rol kan eigenaar van een taak zijn.', 'Projectleiding bewaakt projectkritische acties.', 'De inhoudelijke eigenaar sluit de taak pas na aantoonbare afronding.'],
        'checks' => ['Controleer eigenaar, deadline, context en gewenste uitkomst.', 'Controleer afhankelijkheden voordat de taak wordt afgesloten.', 'Escaleren wanneer een taak gevolgen krijgt voor scope, planning, geld, veiligheid of kwaliteit.'],
        'rules' => ['Een taak beschrijft een actie; de broninformatie blijft op het bronobject.', 'Geen anonieme open acties zonder eigenaar.', 'Afgerond betekent uitgevoerd en gecontroleerd, niet alleen “doorgegeven”.'],
        'mistakes' => ['Besluiten alleen als taaktekst bewaren.', 'Taken zonder project- of objectcontext maken.', 'Taken sluiten terwijl vervolgcontrole nog openstaat.'],
        'related' => ['Dagelijkse taken', 'Projectafwijking verwerken', 'Restpunt afronden'],
      ],
      'relaties' => [
        'title' => 'Relaties, contactpersonen en CRM',
        'purpose' => 'Leg organisaties, personen, commerciële kansen en contacthistorie één keer centraal vast en hergebruik die informatie door heel Office.',
        'use' => ['Gebruik Relaties voor opdrachtgevers, leveranciers, onderaannemers en andere organisaties.', 'Koppel contactpersonen aan de juiste organisatie.', 'Gebruik de commerciële funnel voor leads en kansen tot opdracht of afsluiting.'],
        'roles' => ['Bedrijfsbureau/CRM bewaakt dat relaties centraal en schoon worden vastgelegd.', 'Calculator, Projectleiding en Finance hergebruiken dezelfde relatiegegevens.', 'Iedere gebruiker legt relevante contactmomenten in de juiste context vast.'],
        'checks' => ['Zoek eerst of organisatie of contactpersoon al bestaat.', 'Controleer naam, contactgegevens en rol van de contactpersoon.', 'Koppel kans, offerte, gebouw en project aan bestaande relaties.', 'Leg betaaltermijn en andere klantspecifieke afspraken op de juiste bron vast.'],
        'rules' => ['Geen dubbele relaties voor dezelfde juridische of feitelijke organisatie.', 'De mailbox is geen CRM-dossier; relevante communicatie wordt gekoppeld.', 'Klantgegevens worden niet opnieuw in ieder project overgetypt.'],
        'mistakes' => ['Een nieuwe relatie aanmaken omdat de spelling afwijkt.', 'Contactpersoon als losse organisatie registreren.', 'Commerciële opvolging alleen in persoonlijke notities bewaren.'],
        'related' => ['Relatie vastleggen', 'Kans opvolgen', 'Mail koppelen', 'Klantportaal'],
      ],
      'mail' => [
        'title' => 'Mail en communicatie',
        'purpose' => 'Behandel e-mail als communicatiekanaal en koppel relevante informatie aan de juiste bedrijfscontext zonder de mailbox zelf als dossier te gebruiken.',
        'use' => ['Gebruik Mail voor lezen, beantwoorden, opstellen en verwerken van berichten.', 'Koppel relevante berichten aan relatie, kans, project of ander object.', 'Sla relevante bijlagen als document in de juiste Office-context op.'],
        'roles' => ['Iedere gebruiker verwerkt eigen communicatie zorgvuldig.', 'Projectleiding bewaakt projectkritische communicatie.', 'Bedrijfsbureau bewaakt generieke intake en correcte routering waar van toepassing.'],
        'checks' => ['Controleer afzender, ontvangers, onderwerp en projectcontext.', 'Controleer vóór koppelen of het juiste object is gekozen.', 'Controleer of bijlagen als zelfstandig document moeten worden bewaard.', 'Gebruik server-side mailboxacties zonder de bewezen reader/compose/linking-basis te verstoren.'],
        'rules' => ['Fysieke verwijdering van mail is niet de standaard werkmethode.', 'Een e-mailkoppeling vervangt geen formeel besluit of documentobject.', 'Mailweergave mag geen render-time afhankelijkheid creëren van externe routes.'],
        'mistakes' => ['Projectafspraken alleen in de inbox laten staan.', 'Een verkeerd project koppelen omdat alleen op onderwerptekst is afgegaan.', 'Bijlagen niet afzonderlijk archiveren wanneer ze projectdocumenten zijn.'],
        'related' => ['E-mail koppelen', 'Documenten', 'Relaties', 'Projecten'],
      ],
      'finance' => [
        'title' => 'Finance en financiële controle',
        'purpose' => 'Maak financiële verplichtingen, voortgang, afwijkingen, cashflow en beslissingen zichtbaar vóórdat verrassingen pas bij de factuur of nacalculatie ontstaan.',
        'use' => ['Gebruik Finance voor financieel projectbeeld, verplichtingen, facturatie, betalingen en beslissingen.', 'Gebruik de werkbegroting als operationele financiële basis.', 'Leg afwijkingen en fasepoortbesluiten expliciet vast.'],
        'roles' => ['Projectleider stuurt op projectresultaat.', 'Calculator/Inkoper beheerst prijs en verplichtingen.', 'Finance/Controller bewaakt financiële consistentie en signalen.', 'Directie beslist uitzonderingen en escalaties waar nodig.'],
        'checks' => ['Controleer verplichtingen vóór opdracht.', 'Vergelijk actuele kosten en forecast met werkbegroting.', 'Controleer BTW, G-rekening, betaaltermijnen en factuurstatus.', 'Beoordeel financiële afwijkingen op budget, cashflow en resultaat.'],
        'rules' => ['Financiële controle is preventief, niet alleen administratief achteraf.', 'Afwijkingen worden niet stil weggeboekt in andere regels.', 'Besluiten hebben bron, eigenaar, onderbouwing en opvolging.'],
        'mistakes' => ['Alleen facturen controleren en open verplichtingen vergeten.', 'Werkbegroting aanpassen om een afwijking onzichtbaar te maken.', 'Cashflow-effect negeren omdat het projectresultaat nog positief lijkt.'],
        'related' => ['Werkbegroting', 'Inkoop controleren', 'Financiële afwijking beslissen', 'BTW en G-rekening'],
      ],
      'kwaliteit' => [
        'title' => 'Kwaliteit, restpunten en oplevering',
        'purpose' => 'Borg kwaliteit onafhankelijk van uitvoeringsdruk door bevinding, bewijs, herstel en nacontrole in één controleketen vast te leggen.',
        'use' => ['Gebruik Kwaliteit voor inspecties, afwijkingen, gebreken, restpunten en oplevercontrole.', 'Koppel bevindingen aan project, gebouw en zo specifiek mogelijk aan locatie of onderdeel.', 'Gebruik bewijsfoto’s en documenten om herstel aantoonbaar te maken.'],
        'roles' => ['KAM/Kwaliteit voert onafhankelijke controle uit.', 'Uitvoerder organiseert herstel.', 'Projectleider bewaakt impact op planning, klant en projectresultaat.'],
        'checks' => ['Leg oorzaak, gevolg en urgentie vast.', 'Controleer of herstelverantwoordelijke en termijn duidelijk zijn.', 'Sluit alleen na bewijs en nacontrole.', 'Bewaak garanties en terugkerende afwijkingen voor structurele oorzaken.'],
        'rules' => ['Niet uitgevoerd onderhoud of herstel krijgt expliciet gevolg en risico mee.', 'Kwaliteit wordt niet goedgekeurd alleen omdat de planning door moet.', 'Nacontrole is een eigen controlehandeling.'],
        'mistakes' => ['Alleen “niet goed” noteren zonder oorzaak of locatie.', 'Een restpunt sluiten na melding van herstel zonder controle.', 'Foto’s bewaren zonder koppeling aan de bevinding.'],
        'related' => ['Restpunt vastleggen', 'Restpunt afronden', 'KAM/Kwaliteit', 'Documenten'],
      ],
    ];
  }
}
