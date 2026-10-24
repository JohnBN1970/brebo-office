import SwiftUI
import MapKit

/// Employee navigation reads existing Office assignments and native observations.
/// Switching tabs has no effect on location monitoring or device identity.
struct OnSiteWorkspaceView: View {
    let employeeId: String
    let displayName: String
    let language: String
    let projects: [OnSiteBootstrapProject]
    let states: [String: CrewPresenceState]
    let events: [CrewPresenceEvent]
    let automaticPresence: Bool
    let locationDescription: String
    let locationGuidance: String
    let locationActionTitle: String
    let pendingCount: Int
    let syncError: String?
    let connectionError: String?
    let locationAction: () -> Void

    @State private var selectedTab = OnSiteTab.today
    @State private var registrationDate = Date()

    var body: some View {
        TabView(selection: $selectedTab) {
            NavigationStack { todayView }
                .tabItem { Label("Vandaag", systemImage: "sun.max") }
                .tag(OnSiteTab.today)
            NavigationStack { projectsView }
                .tabItem { Label("Projecten", systemImage: "building.2") }
                .tag(OnSiteTab.projects)
            NavigationStack { registrationsView }
                .tabItem { Label("Registraties", systemImage: "clock.arrow.circlepath") }
                .tag(OnSiteTab.registrations)
            NavigationStack { profileView }
                .tabItem { Label("Mijn profiel", systemImage: "person.crop.circle") }
                .tag(OnSiteTab.profile)
        }
    }

    private var todayView: some View {
        List {
            Section {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Hallo, \(displayName)").font(.title2.bold())
                    Text(Date.now.formatted(.dateTime.weekday(.wide).day().month(.wide)))
                        .foregroundStyle(.secondary)
                }
                .padding(.vertical, 8)
            }
            Section("Aanwezigheid") {
                if !automaticPresence {
                    Label("Automatische aanwezigheid uit", systemImage: "location.slash")
                    Text(locationGuidance).font(.callout).foregroundStyle(.secondary)
                    Button(locationActionTitle, action: locationAction)
                } else if activeProjects.isEmpty {
                    let zones = projects.flatMap(\.zones)
                    let knownOutside = !zones.isEmpty && zones.allSatisfy { states[$0.id] == .outside }
                    Label(projects.isEmpty ? "Geen project toegewezen" : (zones.isEmpty ? "Nog geen projectzone ingesteld" : (knownOutside ? "Buiten de projectzones" : "Locatie wordt gecontroleerd")),
                          systemImage: knownOutside ? "mappin.slash" : "location")
                    Text("Binnenkomst en vertrek worden automatisch herkend bij je toegewezen projecten.")
                        .font(.callout).foregroundStyle(.secondary)
                } else {
                    ForEach(activeProjects) { project in
                        NavigationLink {
                            OnSiteProjectDetailView(project: project)
                        } label: {
                            Label {
                                VStack(alignment: .leading, spacing: 4) {
                                    Text("Aanwezig op").font(.caption).foregroundStyle(.secondary)
                                    Text(project.name).font(.headline)
                                }
                            } icon: {
                                Image(systemName: "checkmark.circle.fill").foregroundStyle(.green)
                            }
                        }
                    }
                }
            }
            if syncError != nil || connectionError != nil {
                Section("Aandacht nodig") {
                    Button { selectedTab = .profile } label: {
                        Label {
                            VStack(alignment: .leading, spacing: 4) {
                                Text(pendingCount > 0 ? "\(pendingCount) registraties wachten op verzending" : "Verbinding met Office controleren")
                                Text("Bekijk de appstatus in Mijn profiel.")
                                    .font(.caption).foregroundStyle(.secondary)
                            }
                        } icon: { Image(systemName: "exclamationmark.icloud") }
                    }
                    .accessibilityIdentifier("onsite.sync-attention")
                }
            }
            Section("Mijn projecten") {
                projectRows
            }
            Section("Laatste registratie") {
                if let event = events.max(by: { $0.occurredAt < $1.occurredAt }) {
                    eventRow(event)
                    Button("Bekijk registraties") { selectedTab = .registrations }
                } else {
                    Text("Nog geen IN/UIT-waarneming sinds het openen van OnSite.")
                        .foregroundStyle(.secondary)
                }
            }
        }
        .navigationTitle("Vandaag")
        .accessibilityIdentifier("onsite.today")
    }

    private var projectsView: some View {
        List {
            Section {
                projectRows
            } header: {
                Text("Toegewezen vanuit Office")
            } footer: {
                Text("Je ziet alleen de projecten die Office aan je heeft toegewezen.")
            }
        }
        .navigationTitle("Projecten")
        .accessibilityIdentifier("onsite.projects")
    }

    @ViewBuilder
    private var projectRows: some View {
        if projects.isEmpty {
            Text("Geen projecten toegewezen.").foregroundStyle(.secondary)
        } else {
            ForEach(projects) { project in
                NavigationLink {
                    OnSiteProjectDetailView(project: project)
                } label: {
                    Label {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(project.name)
                            Text(project.zones.isEmpty ? "Nog geen projectzone ingesteld" : (project.zones.count == 1 ? "1 projectzone" : "\(project.zones.count) projectzones"))
                                .font(.caption).foregroundStyle(.secondary)
                        }
                    } icon: { Image(systemName: "building.2") }
                }
            }
        }
    }

    private var registrationsView: some View {
        List {
            Section {
                DatePicker("Datum", selection: $registrationDate, displayedComponents: .date)
            } footer: {
                Text("IN/UIT-waarnemingen sinds het openen van OnSite. Het volledige overzicht wordt in Office bewaard. Aanwezigheid is nog geen goedgekeurde werktijd.")
            }
            if selectedEvents.isEmpty {
                Section {
                    ContentUnavailableView("Geen registraties", systemImage: "clock",
                                           description: Text("Voor deze dag zijn op dit scherm geen waarnemingen beschikbaar."))
                }
            } else {
                Section("Binnenkomst en vertrek") {
                    ForEach(selectedEvents) { event in eventRow(event) }
                }
            }
        }
        .navigationTitle("Registraties")
        .accessibilityIdentifier("onsite.registrations")
    }

    private func eventRow(_ event: CrewPresenceEvent) -> some View {
        HStack(alignment: .top, spacing: 12) {
            Text(event.kind == .enteredProject ? "IN" : "UIT")
                .font(.caption.bold())
                .padding(8)
                .background(event.kind == .enteredProject ? Color.green.opacity(0.12) : Color.orange.opacity(0.12),
                            in: RoundedRectangle(cornerRadius: 8))
            VStack(alignment: .leading, spacing: 4) {
                Text(projects.first { $0.id == event.projectId }?.name ?? "Project \(event.projectId)")
                    .font(.headline)
                Text(event.occurredAt.formatted(date: .abbreviated, time: .shortened))
                    .font(.caption).foregroundStyle(.secondary)
            }
            Spacer()
        }
        .padding(.vertical, 3)
    }

    private var profileView: some View {
        List {
            Section("Medewerker") {
                LabeledContent("Naam", value: displayName)
                LabeledContent("Taal vanuit Office", value: Locale.current.localizedString(forIdentifier: language) ?? language)
            }
            Section("Locatietoegang") {
                LabeledContent("Toestemming", value: locationDescription)
                Text(locationGuidance).font(.callout).foregroundStyle(.secondary)
                Button(locationActionTitle, action: locationAction)
            }
            Section("Appstatus") {
                LabeledContent("Koppeling", value: "Gekoppeld aan Office")
                LabeledContent("Wacht op verzending", value: "\(pendingCount)")
                if let error = syncError {
                    Label(error, systemImage: "exclamationmark.icloud").foregroundStyle(.orange)
                }
                if let error = connectionError {
                    Label(error, systemImage: "wifi.exclamationmark").foregroundStyle(.orange)
                }
                Text("Verzending wordt automatisch opnieuw geprobeerd zodra er verbinding is.")
                    .font(.caption).foregroundStyle(.secondary)
            }
            Section("Over OnSite") {
                LabeledContent("Versie", value: appVersion)
                if OnSiteConfiguration.isOfficeTest {
                    LabeledContent("Omgeving", value: "Office-test")
                }
                Text("OnSite registreert aanwezigheid bij toegewezen projectzones. Er wordt geen routehistorie opgeslagen.")
                    .font(.caption).foregroundStyle(.secondary)
            }
        }
        .navigationTitle("Mijn profiel")
        .accessibilityIdentifier("onsite.profile")
    }

    private var activeProjects: [OnSiteBootstrapProject] {
        projects.filter { project in project.zones.contains { states[$0.id] == .present } }
    }

    private var selectedEvents: [CrewPresenceEvent] {
        events.filter { Calendar.current.isDate($0.occurredAt, inSameDayAs: registrationDate) }
            .sorted { $0.occurredAt > $1.occurredAt }
    }

    private var appVersion: String {
        let version = Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "—"
        let build = Bundle.main.object(forInfoDictionaryKey: "CFBundleVersion") as? String ?? "—"
        return "\(version) (\(build))"
    }
}

private enum OnSiteTab: Hashable { case today, projects, registrations, profile }

struct OnSiteProjectDetailView: View {
    let project: OnSiteBootstrapProject

    var body: some View {
        List {
            Section("Overzicht") {
                Text(project.name).font(.headline)
                Text("Binnenkomst en vertrek worden herkend binnen onderstaande projectzones.")
                    .font(.callout).foregroundStyle(.secondary)
            }
            if project.zones.isEmpty {
                Section {
                    Text("Voor dit project is nog geen zone ingesteld in Office.")
                        .foregroundStyle(.secondary)
                }
            }
            ForEach(project.zones) { zone in
                Section(zone.name) {
                    Map(initialPosition: .region(MKCoordinateRegion(
                        center: CLLocationCoordinate2D(latitude: zone.latitude, longitude: zone.longitude),
                        latitudinalMeters: max(zone.radiusMetres * 4, 400),
                        longitudinalMeters: max(zone.radiusMetres * 4, 400)))) {
                        MapCircle(center: CLLocationCoordinate2D(latitude: zone.latitude, longitude: zone.longitude),
                                  radius: zone.radiusMetres)
                            .foregroundStyle(.purple.opacity(0.2))
                        Marker(zone.name, coordinate: CLLocationCoordinate2D(latitude: zone.latitude, longitude: zone.longitude))
                    }
                    .frame(height: 200)
                    .accessibilityLabel("Kaart van projectzone \(zone.name)")
                    LabeledContent("Straal", value: "\(Int(zone.radiusMetres)) meter")
                    Button { openRoute(to: zone) } label: {
                        Label("Route naar projectzone", systemImage: "arrow.triangle.turn.up.right.diamond")
                    }
                }
            }
        }
        .navigationTitle("Project")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func openRoute(to zone: OnSiteBootstrapZone) {
        let destination = MKMapItem(placemark: MKPlacemark(
            coordinate: CLLocationCoordinate2D(latitude: zone.latitude, longitude: zone.longitude)))
        destination.name = "\(project.name) · \(zone.name)"
        destination.openInMaps(launchOptions: [MKLaunchOptionsDirectionsModeKey: MKLaunchOptionsDirectionsModeDriving])
    }
}
