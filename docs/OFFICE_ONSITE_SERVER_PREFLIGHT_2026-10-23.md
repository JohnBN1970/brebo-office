# Office-test OnSite serverpreflight — 23 oktober 2026

Bron: door de gebruiker teruggegeven JSON-uitvoer van het alleen-lezen Moba-preflightcommando. Dit is serverbewijs van de gebruiker; er is vanuit Codex geen databaseverbinding uitgevoerd.

## Vastgestelde toestand

- Status: SCHEMA_VERIFIED; blockers: [].
- office_onsite_event_inbox: COMPATIBLE, 0 rijen.
- office_onsite_device_tokens: COMPATIBLE, 0 rijen.
- office_inzet_people: 0 medewerkers, daarvan 0 actief.
- Geen verweesde of inactieve medewerkerverwijzingen in de twee lege Office-tabellen.
- Bruikbare Office-apparaten: 0; bruikbare apparaten met projecttoewijzing: 0.
- Projecttoewijzingscontrole: CHECKED. Bij nul apparaten bewijst dit geen werkende project-/apparaatkoppeling.
- Legacy onsite_device_tokens, onsite_event_inbox en onsite_employees: PRESENT_NOT_IMPORTED.
- Drupal KeyValue-apparaten: NOT_CHECKED_EXTERNAL_AUTHORITY.
- Identiteitsbrug: UNVERIFIED_REQUIRES_SEPARATE_DEVICE_AND_PERSON_ACCEPTANCE.
- created_tables: []; activation: UNCHANGED; data_changes: NONE.

## Betekenis en vervolgstap

De migratie is voor deze momentopname niet nodig: beide doeltabellen bestaan al en zijn compatibel. Voer geen apply uit om dezelfde toestand nogmaals te realiseren. De preflight geeft geen bewijs wanneer of door wie de tabellen eerder zijn aangemaakt.

Schemagereedheid is bevestigd. De bestaande apparaat-/medewerkerkoppeling is niet bevestigd: de Office-kant is leeg en legacy-tabellen zijn alleen op aanwezigheid gecontroleerd. Aanwezigheid bewijst geen rijen, credentialcompatibiliteit of identiteit. Drupal is niet bevraagd.

Laat alle activering uit. Onderzoek als volgende afzonderlijke stap alleen-lezen de bestaande legacy-schema's en gegevensauthority, met uitsluitend metadata en geaggregeerde aantallen. Leg daarna een expliciete, beoordeelde identiteitsmapping vast voordat een import of Office-apparaatactivatie wordt overwogen. Geen numerieke ID-gelijkheid aannemen en geen automatische import, medewerkercreatie of tokenuitgifte.

Er is in deze stap geen migratie, productiehandeling, featureflagwijziging of activering uitgevoerd. Zie ook OFFICE_ONSITE_TEST_MIGRATION_2026-10-22.md voor de bestaande uitvoer- en rollbackprocedure.
