# BREBO Office HTML-mail

Status: gecontroleerde presentatielaag voor uitgaande e-mail.

## Verdeling van verantwoordelijkheden

- AI of medewerker maakt de variabele inhoud binnen een vastgesteld mailsjabloon.
- `MailHtmlRenderer` maakt van die gecontroleerde platte tekst een vaste BREBO HTML-mail.
- Mime Mail verzorgt de MIME/HTML-formattering en de plaintext-alternatieflaag.
- SMTP verzorgt uitsluitend het transport.
- `OutboundMailService` bewaakt mandaat, expliciete vrijgave, dossierregistratie en verzendstatus.

## Veiligheidsprincipe

De AI-tekst wordt door de renderer altijd als platte tekst behandeld en HTML-geescaped. Daardoor kan een AI-concept niet zelfstandig scripts, verborgen pixels, externe afbeeldingen of willekeurige HTML in de definitieve organisatiemail injecteren.

De vaste shell bevat vooralsnog een tekstuele BREBO-header, onderwerp, berichtinhoud en een beschermde footer met `info@brebobv.nl`. Een officieel logo of aanvullende juridische/footerbouwstenen worden pas toegevoegd uit een vastgestelde BREBO-bron; ze worden niet door AI bedacht.

## Mime Mail

Mime Mail moet in Mail System als formatter voor BREBO-uitgaande mail worden gebruikt. De module krijgt dus al geldige HTML aangeleverd; Mime Mail is niet verantwoordelijk voor het schrijven van de inhoud. Dit volgt de gekozen architectuurscheiding:

`dossier/context -> AI-concept -> menselijke controle -> BREBO HTML-shell -> Mime Mail -> SMTP`

SMTP blijft apart vergrendeld via runtime-activering en Drupal-configuratie. Zonder beide expliciete schakelaars kan de verzendservice niets extern versturen.
