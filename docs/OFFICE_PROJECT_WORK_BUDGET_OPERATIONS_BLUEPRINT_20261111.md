# BREBO Office — projectwerkbegroting als werk- en controleorgaan

Status: vastgestelde functionele richting, implementatie nog niet afgerond. Office is de bron; geen Drupal-afhankelijkheid.

## Projectdossier
Project is de hoofdcontext. Gebouw en gebouwzones leveren locaties. Projectactiviteiten vormen de gedeelde sleutel voor werkbegroting, planning, taken, mijlpalen, inzet, inkoop, leveringen, uren en kwaliteitscontrole. Een project kan meerdere gebouwen omvatten; zones behoren aan gebouwen.

## 1. Van calculatie naar vergrendelde werkbegroting
Na opdrachtacceptatie wordt een immutable baseline aangemaakt uit de geaccepteerde calculatieversie. Directe opdrachten zonder calculatie volgen een expliciete, geautoriseerde begrotingsvaststelling. Elke baseline-regel behoudt bronregel-ID, activiteit, kostensoort, hoeveelheid, eenheid, prijs en bedrag. Wijzigingen worden als afzonderlijke goedgekeurde mutaties bijgehouden; nooit stilzwijgend overschrijven.

## 2. Geconsolideerde materiaalstaat
Materiaalregels met gelijke canonieke artikelidentiteit, technische specificatie en eenheid worden per project getotaliseerd. Gelijke vrije omschrijvingen alleen zijn onvoldoende om automatisch samen te voegen. Ontbrekende artikelidentiteit leidt tot voorstel voor handmatige matching, geen automatische fusie. Bewaar per groep een allocatie naar alle oorspronkelijke calculatieregels, activiteiten en gebouwzones.

Per artikel: begrote hoeveelheid, besteld, ontvangen, verwerkt/gebruikt, resterend te bestellen, gereserveerd, verwachte inkoopprijs, verplichtingen en afwijkingen. Eenheidsconversie alleen met expliciet gevalideerde conversieregel. Geen negatieve resterende aantallen zonder zichtbaar overschrijdingssignaal.

## 3. Inkooporders en deelleveringen
Inkooporderkop: leverancier, project, status, datum, valuta, leveradres (gebouw), verwachte leverdatum.
Orderregel: artikelidentiteit, specificatie, hoeveelheid, eenheid, overeengekomen prijs excl. btw, begrotingsallocaties.
Ontvangst: orderregel, datum, werkelijk ontvangen hoeveelheid, locatie/zone, bewijs en verantwoordelijke.
Meerdere ontvangstregels per orderregel toegestaan. Retouren en correcties als afzonderlijke gebeurtenissen met audittrail. Besteld, ontvangen, gefactureerd en betaald blijven aparte staten. Voorkom dubbele ontvangst en overschrijding zonder expliciete afwijkingsafhandeling.

## 4. Planning naast werkbegroting
Activiteit: project, gebouw, zone, hoeveelheid, eenheid, start/eind, afhankelijkheden, verantwoordelijke, status.
Taken en mijlpalen hangen aan activiteiten. Materiaalbehoefte en leverdatum zijn planinputs; ontvangsten beïnvloeden leveringsgereedheid, niet automatisch het percentage voltooid. Werkelijk gereed = geautoriseerd vastgestelde voortgang. Uren uit OnSite blijven voorlopig tot goedkeuring.

Views: Gantt, kalender, Kanban, lijst; filters project, gebouw/zone, medewerker, periode en status.

## 5. Financiële bewaking
Kolommen: oorspronkelijk budget, goedgekeurde wijzigingen, actuele begroting, verplicht, ontvangen, gefactureerd/geboekt, nog te bestellen, resterend werk, prognose eindkosten, afwijking.
Financiële verplichting is geen geboekte kost. Geen dubbeltelling tussen order, ontvangst en factuur. Prognoses alleen als vereiste bronnen volledig en gereconcilieerd zijn; anders null / niet beschikbaar, nooit fictief nul.
Kosten inclusief arbeid, materieel, onderaanneming en overige posten naast materialen.

## 6. UI
Projectheader met gebouw/locatie en drie compacte totalen. Tabs: Werkbegroting, Materiaal & Inkoop, Planning, Projectbewaking.
Werkbegroting: brede horizontaal scrollbare tabel met vastgezette identificatiekolommen, subtotaal per activiteit/kostensoort, filterregel, uitklapbare detailregels.
Materiaal & Inkoop: geconsolideerde artikelregels met uitklapbare bronallocaties, orders, deelleveringen, prijzen en resterende hoeveelheden.
Planning: gekoppelde activiteiten/taken/mijlpalen per gebouwzone.
Projectbewaking: budget vs verplichtingen vs werkelijk vs prognose, afwijkingssignalen.

## 7. Realisatievolgorde en acceptatie
1. Schema- en ACL-inventarisatie, contracttests voor bestaande project/Calc/Finance/Inkoop/OnSite-bronnen.
2. Canonieke artikelidentiteit, consolidatie-readmodel en bronallocaties, met tests op niet-gelijke specificaties.
3. Inkooporder/ontvangst-transacties, statusmachine, audittrail, rechten en idempotentie.
4. Planning-activiteit/gebouwzone/taken/mijlpalen-koppelingen.
5. Finance- en urenreconciliatie, prognosegates en totaalcontroles.
6. UI per tab, gebruikersacceptatie en geïsoleerde Office-test uitrol.

Niet als voltooid beschouwen zolang de financiële bronnen en deelleveringen niet functioneel zijn aangesloten. Geen productie-uitrol of databasemutatie zonder gecontroleerde migratie en verificatie.
