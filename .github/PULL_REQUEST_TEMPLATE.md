## BREBO Office — ontwikkeloverdracht

> Raadpleeg `AGENTS.md`, `docs/DEVELOPMENT_POLICY.md` en `docs/OFFICE_DEVELOPMENT_STATUS.md`. De actuele GitHub- en Office-test-status blijft leidend.

### Scope en bestaande werkzaamheden

- Module / scherm / gebruikersketen:
- Getoetste `develop`-SHA:
- Bestaande implementaties en overlappende PR's gecontroleerd:
- Wat deze PR toevoegt (geen dubbel werk):

### Uitvoering en controle

- [ ] Relevante code en migraties geïntegreerd in bestaande architectuur
- [ ] Autorisatie, CSRF, audittrail en brongegevens gecontroleerd waar relevant
- [ ] Centrale `Office-test source gate` geslaagd
- [ ] Geen nieuwe deployworkflow of automatische productieactie
- [ ] Relevante regressie-/contracttests uitgevoerd

### Na merge (apart vastleggen, niet vooraf aanvinken)

- [ ] Merge-SHA:
- [ ] Centrale Office-test-run:
- [ ] Actieve VPS-release-SHA komt overeen met merge:
- [ ] Ingelogde functionele acceptatie op echte Office-test-route en rol:
- [ ] Resterende blokkades of handmatige acceptatie expliciet vermeld:
- [ ] `docs/OFFICE_DEVELOPMENT_STATUS.md` bijgewerkt als overdracht wezenlijk verandert

### Status voor andere ontwikkelchats

**Werkt:** (uitsluitend aantoonbaar geaccepteerd)

**Getest:** (specifieke tests / runs)

**Blokkeert:** (concrete beperking en eerstvolgende stap)

**Let op:** groen CI, HTTP 302 of een VPS-SHA-match alleen bewijst geen ingelogde gebruikersacceptatie. Markeer productie nooit als vrijgegeven door een develop-merge.
