# BREBO Resident Service

Centrale module voor bewoners, woningen, toegang, meldingen, klachten, schade, service en nazorg.

## Datamodel

- `brebo_residence`: projectgebonden woning/gebruiksobject en toegangsstatus.
- `brebo_resident`: minimale bewoners/contactgegevens.
- `brebo_resident_case`: melding, klacht, schade, service, toegang of nazorg.
- `brebo_resident_case_event`: append-only tijdlijn voor contact, acties en statuswijzigingen.
- `brebo_photo`: onveranderlijk origineel fotobewijs.
- `brebo_photo_annotation`: niet-destructieve markeringen met JSON-geometrie en koppeling aan dossierobjecten.

## Proces

Melding -> woning/bewoner -> eigenaar -> actie -> oplossing -> bewijs -> bevestiging -> sluiten.

Een dossier is niet gesloten uitsluitend omdat intern een taak gereed is; `resident_confirmed` bewaart afzonderlijk of de oplossing door de bewoner is bevestigd wanneer dat proces dit vereist.

Fotoannotaties wijzigen het origineel nooit. Pijl, cirkel/rechthoek, vrije lijn, pin/nummer, tekst en maatlijn kunnen als `annotation_type` worden opgeslagen. `geometry_json` bevat genormaliseerde coordinaten zodat annotaties resolutie-onafhankelijk blijven. `linked_type` + `linked_id` koppelt een markering bijvoorbeeld aan een bewonersdossier, taak, NCR, opleverpunt, schade of meerwerksignaal.

## Privacy

Bewonersgegevens zijn projectgebonden en worden minimaal opgeslagen. Gevoelige gegevens vallen onder de aparte permission `manage brebo resident sensitive data`. Bewaar geen medische of andere bijzondere persoonsgegevens tenzij daarvoor later expliciet een juridisch en functioneel proces wordt ingericht.
