# BREBO Resident Service

Centrale module voor gebouwen, BAG-adressen, woningen/gebruiksobjecten, bewoners, toegang, meldingen, klachten, schade, service en nazorg.

## Canonieke keten

`brebo_building (node/building_nid)` -> `brebo_building_address` -> `brebo_residence` -> `brebo_resident` -> `brebo_resident_case`.

Er wordt geen tweede gebouwidentiteit onderhouden. `building_nid` verwijst altijd naar het bestaande canonieke BREBO-gebouw.

## Adres-/scope intake via communicatie

De service `brebo_resident_service.address_scope_intake` is kanaalonafhankelijk. Mail, communicatie-node, notitie, WhatsApp-import, documentextractie, opdrachttekst of handmatige invoer kunnen dezelfde `propose()`-methode aanroepen.

Voorbeeld: `Bilderdijkstraat 87 t/m 97 oneven, Amsterdam`.

1. `AddressScopeParser` detecteert straat, range, pariteit, postcode en plaats waar aanwezig.
2. `PdokBagResolver` bevraagt de officiele PDOK BAG OGC API. Er worden nooit synthetische huisnummers gegenereerd.
3. `brebo_address_scope_intake` bewaart bron, letterlijk gevonden tekst, resolutieresultaat en status.
4. `materialize()` schrijft na bevestiging de gevonden officiele adressen naar `brebo_building_address` en de gebruikseenheden naar `brebo_residence`.
5. BAG-identiteiten blijven op de woning bewaard voor deduplicatie en herleidbaarheid.

Vrije communicatie materialiseert standaard niet stilzwijgend tientallen woningen: eerst wordt een voorstel/resolutie opgeslagen. Een geautoriseerde workflow kan later expliciet automatische materialisatie gebruiken.

## Bewonersdossier

- `brebo_resident`: minimale bewoners/contactgegevens.
- `brebo_resident_case`: melding, klacht, schade, service, toegang of nazorg.
- `brebo_resident_case_event`: append-only tijdlijn voor contact, acties en statuswijzigingen.

Proces: melding -> woning/bewoner -> eigenaar -> actie -> oplossing -> bewijs -> bevestiging -> sluiten.

## Foto's en markeringen

- `brebo_photo`: onveranderlijk origineel fotobewijs.
- `brebo_photo_annotation`: niet-destructieve markeringen met JSON-geometrie en koppeling aan dossierobjecten.

Fotoannotaties wijzigen het origineel nooit. Pijl, cirkel/rechthoek, vrije lijn, pin/nummer, tekst en maatlijn kunnen als `annotation_type` worden opgeslagen. `geometry_json` bevat genormaliseerde coordinaten zodat annotaties resolutie-onafhankelijk blijven.

## Privacy

Bewonersgegevens worden minimaal opgeslagen. Gevoelige gegevens vallen onder `manage brebo resident sensitive data`.
