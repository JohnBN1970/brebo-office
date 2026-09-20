import SwiftUI

struct CrewContentView: View {
    @StateObject private var locationMonitor = CrewLocationMonitor()
    @StateObject private var identityStore = OnSiteIdentityStore()
    @State private var phoneNumber = ""
    @State private var verificationCode = ""

    var body: some View {
        NavigationStack {
            Group {
                if !hasLocationPermission {
                    permissionView
                } else {
                    switch identityStore.state {
                    case .unlinked, .requestingCode:
                        linkView
                    case .codeSent(let phoneNumber, let challengeId),
                         .verifying(let phoneNumber, let challengeId):
                        verificationView(phoneNumber: phoneNumber, challengeId: challengeId)
                    case .linked(let employeeId, let displayName, _):
                        operationalView(employeeId: employeeId, displayName: displayName)
                    }
                }
            }
            .padding()
            .navigationTitle("OnSite")
            .task {
                await identityStore.restoreLinkedDevice()
                syncProjectZones()
            }
            .onChange(of: identityStore.assignedProjects) {
                syncProjectZones()
            }
        }
    }

    private var hasLocationPermission: Bool {
        locationMonitor.authorizationStatus == .authorizedAlways ||
        locationMonitor.authorizationStatus == .authorizedWhenInUse
    }

    private var permissionView: some View {
        VStack(spacing: 20) {
            Image(systemName: "mappin.and.ellipse")
                .font(.system(size: 48))

            Text("BREBO OnSite")
                .font(.largeTitle.bold())

            Text("Aantoonbare aanwezigheid op toegewezen projecten, zonder routehistorie.")
                .multilineTextAlignment(.center)

            Button("Locatietoegang voorbereiden") {
                locationMonitor.requestAuthorization()
            }
            .buttonStyle(.borderedProminent)

            Text("Status: \(authorizationText)")
                .font(.caption)

            Spacer()
        }
    }

    private var linkView: some View {
        VStack(alignment: .leading, spacing: 20) {
            Label("Eenmalig koppelen", systemImage: "iphone.gen3")
                .font(.title2.bold())

            Text("Vul het mobiele nummer in dat bij je medewerkerprofiel in Office staat. Daarna werkt OnSite zonder dagelijkse instellingen.")
                .foregroundStyle(.secondary)

            TextField("Mobiel nummer", text: $phoneNumber)
                .keyboardType(.phonePad)
                .textContentType(.telephoneNumber)
                .textFieldStyle(.roundedBorder)

            Button {
                Task { await identityStore.requestCode(phoneNumber: phoneNumber) }
            } label: {
                if case .requestingCode = identityStore.state {
                    ProgressView()
                } else {
                    Text("Stuur verificatiecode")
                }
            }
            .buttonStyle(.borderedProminent)
            .disabled(phoneNumber.filter { $0.isNumber }.count < 8 || isRequesting)

            if let errorMessage = identityStore.errorMessage {
                Text(errorMessage)
                    .font(.caption)
                    .foregroundStyle(.red)
            }

            Spacer()
        }
    }

    private func verificationView(phoneNumber: String, challengeId: String) -> some View {
        VStack(alignment: .leading, spacing: 20) {
            Label("Controleer je sms", systemImage: "message.badge")
                .font(.title2.bold())

            Text("Verificatie voor \(phoneNumber)")
                .foregroundStyle(.secondary)

            TextField("Verificatiecode", text: $verificationCode)
                .keyboardType(.numberPad)
                .textContentType(.oneTimeCode)
                .textFieldStyle(.roundedBorder)

            Button {
                Task {
                    await identityStore.verifyCode(
                        phoneNumber: phoneNumber,
                        challengeId: challengeId,
                        code: verificationCode
                    )
                }
            } label: {
                if case .verifying = identityStore.state {
                    ProgressView()
                } else {
                    Text("Koppel deze iPhone")
                }
            }
            .buttonStyle(.borderedProminent)
            .disabled(verificationCode.filter { $0.isNumber }.count != 6 || isVerifying)

            if let errorMessage = identityStore.errorMessage {
                Text(errorMessage)
                    .font(.caption)
                    .foregroundStyle(.red)
            }

            Button("Ander nummer") {
                verificationCode = ""
                identityStore.reset()
            }

            Spacer()
        }
    }

    private func operationalView(employeeId: String, displayName: String) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                HStack {
                    Image(systemName: "checkmark.circle.fill")
                    VStack(alignment: .leading) {
                        Text("OnSite is gereed")
                            .font(.title2.bold())
                        Text("Locatietoegang: \(authorizationText)")
                            .foregroundStyle(.secondary)
                    }
                }

                GroupBox("Medewerker") {
                    Label(displayName, systemImage: "person.crop.circle")
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .accessibilityHint("Office medewerker \(employeeId)")
                }

                GroupBox("Toegewezen projecten") {
                    VStack(alignment: .leading, spacing: 8) {
                        if identityStore.assignedProjects.isEmpty {
                            Label("Vandaag geen toegewezen projecten", systemImage: "building.2")
                        } else {
                            ForEach(identityStore.assignedProjects) { project in
                                Label(project.name, systemImage: "building.2")
                                ForEach(project.zones) { zone in
                                    Text("• \(zone.name) · \(Int(zone.radiusMetres)) m")
                                        .font(.caption)
                                        .foregroundStyle(.secondary)
                                }
                            }
                        }
                        Text("Projecten en zones komen rechtstreeks uit Office; OnSite kiest niets zelf.")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }

                GroupBox("Aanwezigheid") {
                    VStack(alignment: .leading, spacing: 8) {
                        if let active = activePresenceText {
                            Label(active, systemImage: "mappin.and.ellipse")
                        } else {
                            Label("Niet binnen een toegewezen projectzone", systemImage: "mappin.slash")
                        }
                        Text("IN/UIT is aanwezigheidsevidence en nog geen geboekte werktijd.")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }

                GroupBox("Laatste waarneming") {
                    Text(lastObservationText)
                        .frame(maxWidth: .infinity, alignment: .leading)
                }
            }
        }
    }

    private func syncProjectZones() {
        let zones = identityStore.assignedProjects.flatMap { project in
            project.zones.map { zone in
                CrewProjectZone(
                    id: zone.id,
                    projectName: project.name,
                    latitude: zone.latitude,
                    longitude: zone.longitude,
                    radiusMetres: zone.radiusMetres
                )
            }
        }
        locationMonitor.monitor(zones)
    }

    private var activePresenceText: String? {
        let zoneNames = Dictionary(
            uniqueKeysWithValues: identityStore.assignedProjects.flatMap { project in
                project.zones.map { ($0.id, project.name) }
            }
        )
        for (zoneId, state) in locationMonitor.states where state == .present {
            return "Aanwezig op \(zoneNames[zoneId] ?? "toegewezen project")"
        }
        return nil
    }

    private var lastObservationText: String {
        guard let event = locationMonitor.events.last else {
            return "Nog geen IN/UIT-waarneming"
        }
        let action = event.kind == .enteredProject ? "IN" : "UIT"
        return "\(action) · \(event.occurredAt.formatted(date: .abbreviated, time: .shortened))"
    }

    private var isRequesting: Bool {
        if case .requestingCode = identityStore.state { return true }
        return false
    }

    private var isVerifying: Bool {
        if case .verifying = identityStore.state { return true }
        return false
    }

    private var authorizationText: String {
        switch locationMonitor.authorizationStatus {
        case .authorizedAlways: return "Altijd toegestaan"
        case .authorizedWhenInUse: return "Tijdens gebruik toegestaan"
        case .denied: return "Geweigerd"
        case .restricted: return "Beperkt"
        case .notDetermined: return "Nog niet gevraagd"
        @unknown default: return "Onbekend"
        }
    }
}
