# Office-native verkoopfacturen

De bestaande verkoopfactuurcode staat in `web/modules/custom/brebo_finance`. De Office-native Finance-concepten onder `office-independent/src/Finance` zijn leveranciersgericht en mogen niet als verkoopfacturen worden behandeld.

## Migratieafspraken

1. Inventariseer bestaande verkoopfactuurtabellen, btw-regimes, nummerreeksen, Moneybird-koppelingen en tests.
2. Behoud de bestaande financiële brongegevens; maak geen parallelle verkoopfactuuradministratie.
3. Maak een Office-native leesmodel met expliciete autorisatie op debiteur en project.
4. Bouw een concepteditor met bedragen in exacte decimalen en expliciet btw-regime per regel.
5. Migreer definitieve vrijgave, PDF en factuurnummering pas na tests op idempotentie en audittrail.
6. Verzend facturen en synchroniseer Moneybird pas via gecontroleerde wachtrij met duplicaatbeveiliging.

## Afbakening

Projectcontracten, meer- en minderwerk en termijnschema blijven in het afzonderlijke contracttraject. Geen Drupal-bootstrap in Office-native controllers. Geen automatische schema-installatie via HTTP. Geen productie-uitrol of verzending zonder acceptatie.

## Eerste acceptatiecriteria

Een geautoriseerde medewerker ziet uitsluitend toegestane concepten. Project- en debiteurkoppelingen zijn gevalideerd. Btw-regimes worden per regel expliciet vastgelegd. Dubbele vrijgave is onmogelijk. Bestaande factuurnummers blijven behouden. Geautomatiseerde tests zijn groen.
