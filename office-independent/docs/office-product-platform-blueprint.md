# BREBO Office: productplatform en klantinrichting (v1)

## Besluit
Het **hele Office-pakket** is het toekomstige verkoopproduct. BREBO is de eerste
klantconfiguratie, niet de bron van generieke bedrijfsregels. De modules CRM,
Calculatie, Offertestudio, Projecten, Planning, Personeel, Documenten, FinanceCenter
en AI blijven onderdeel van hetzelfde platform.

## Technische scheiding
- **Platformkern**: identiteiten, autorisatie, audit, workflow-uitvoering,
  modules, gebeurtenissen, integraties, tenantisolatie en versiebeheer.
- **Modulecontracten**: expliciete acties, invoerschema's, uitvoer, rechten,
  idempotentiesleutels en foutafhandeling. Modules mogen elkaars tabellen niet
  rechtstreeks als workflow-API gebruiken.
- **Klantconfiguratie**: actieve modules, rollen, dashboards, huisstijl,
  procesdefinities, statussen, verbindingen en actie-instellingen.
- **BREBO-inrichting**: afzonderlijk versiebeheerd configuratiepakket, zonder
  databasegegevens, geheimen, klantdocumenten of persoonsgegevens.

## Workflowblauwdruk
- **Knooppunt** = status/processtap met eigenaar en toegangsbeleid.
- **Verbinding** = gebeurtenis + voorwaarden + geordende acties + foutbeleid.
- Acties kunnen *voor* of *na* een statusovergang plaatsvinden; externe
  neveneffecten gebruiken een outbox, retries en idempotentie.
- Financiële en contractuele acties vereisen expliciete rechten en controleerbaar
  bewijs. Een AI-voorstel is nooit zelf goedkeuring.
- Concept, validatie, publicatie en rollback zijn afzonderlijke handelingen.
  Lopende dossiers houden hun workflowversie, tenzij expliciet gemigreerd.

## BREBO-startblauwdruk
Intake -> CRM -> [optionele Calc] -> [optionele Offertestudio] ->
opdrachtacceptatie -> Projecten -> [werkbegroting indien calculatie] ->
uitvoering -> FinanceCenter. Directe opdrachten passeren Calc en Offertestudio.
Inspectie, MJOP en regie kunnen eigen paden gebruiken.

## Verkoopbaarheid: minimale harde eisen
1. Geen vaste BREBO-domeinen, operator-ID's, branding of credentials in generieke code.
2. Tenant-ID op alle tenantgebonden gegevens; elke read/write tenant-scoped,
   inclusief jobs, bestanden, zoekindex en caches.
3. Per tenant autorisatie, versleutelde secrets, auditlog en bewaarbeleid.
4. Module-entitlements en tenantconfiguratie zijn afzonderlijk van applicatiecode.
5. Configuraties importeerbaar/exporteerbaar met schema- en versiecontrole;
   nooit uitvoerbaar als willekeurige PHP of SQL.
6. Geautomatiseerde isolatie-, migratie-, autorisatie- en regressietests vóór
   daadwerkelijke multi-tenant vrijgave.

## Fasering
A. BREBO-keten stabiel maken op office-test met expliciete veiligheidsgrenzen.
B. Configuratieschema en moduleactiecatalogus invoeren zonder bestaande
   CRM/Finance-functionaliteit om te gooien.
C. Grafische editor op de gevalideerde engine aansluiten.
D. Tenantisolatie, provisioning, licenties en operationeel beheer realiseren.
E. Pas na onafhankelijke beveiligings- en acceptatietests commercieel uitrollen.

**Status:** dit is een architectuurcontract, geen claim dat tenantisolatie,
grafische editor, licenties of commerciële uitrol al gerealiseerd zijn.
