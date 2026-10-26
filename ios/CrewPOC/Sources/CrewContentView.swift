import SwiftUI
import UIKit

struct CrewContentView: View {
    @ObservedObject private var locationMonitor = OnSiteRuntime.shared.location
    @ObservedObject private var identityStore = OnSiteRuntime.shared.identity
    @ObservedObject private var presenceUploader = OnSiteRuntime.shared.uploader
    @Environment(\.scenePhase) private var scenePhase
    @State private var phoneNumber = ""
    @State private var verificationCode = ""
    @State private var pendingActivationToken: String?

    var body: some View {
        Group {
            switch identityStore.state {
            case .linked(let employeeId, let displayName, _):
                OnSiteWorkspaceView(
                    employeeId: employeeId, displayName: displayName,
                    language: identityStore.language.rawValue,
                    projects: identityStore.assignedProjects,
                    states: locationMonitor.states, events: presenceUploader.activeRegistrations,
                    pendingEventIds: presenceUploader.pendingEventIds,
                    approvedEventIds: Set(presenceUploader.approvedAtByEventId.keys),
                    automaticPresence: hasAutomaticPresencePermission,
                    locationDescription: authorizationText,
                    locationGuidance: locationGuidanceText,
                    locationActionTitle: locationActionTitle,
                    pendingCount: presenceUploader.pendingCount,
                    syncError: presenceUploader.lastError,
                    connectionError: identityStore.errorMessage,
                    locationAction: handleLocationAction
                )
            default:
                NavigationStack {
                    Group {
                        switch identityStore.state {
                        case .codeSent(let phone, let challenge), .verifying(let phone, let challenge):
                            verificationView(phoneNumber: phone, challengeId: challenge)
                        default:
                            linkView
                        }
                    }
                    .padding()
                    .navigationTitle("OnSite")
                }
            }
        }
        .tint(Color(red: 0.38, green: 0.17, blue: 0.49))
        .onChange(of: scenePhase) { _, phase in
            if phase == .active {
                OnSiteRuntime.shared.wake(forceRefresh: true)
            } else if phase == .background {
                OnSiteRuntime.shared.scheduleRefresh()
            }
        }
        .onOpenURL { url in handleActivationURL(url) }
    }

    private func handleActivationURL(_ url: URL) {
        guard url.scheme?.lowercased() == "brebo-onsite",
              url.host?.lowercased() == "activate",
              let components = URLComponents(url: url, resolvingAgainstBaseURL: false),
              let token = components.queryItems?.first(where: { $0.name == "token" })?.value,
              !token.isEmpty else {
            return
        }

        pendingActivationToken = token
        Task {
            await identityStore.activate(activationToken: token)
            if case .linked = identityStore.state {
                pendingActivationToken = nil
                OnSiteRuntime.shared.wake(forceRefresh: true)
            }
        }
    }

    private var hasAutomaticPresencePermission: Bool {
        locationMonitor.authorizationStatus == .authorizedAlways
    }

    private var locationGuidanceText: String {
        switch locationMonitor.authorizationStatus {
        case .notDetermined:
            return "Geef OnSite locatietoegang om automatisch IN/UIT op toegewezen projectzones te herkennen. De rest van de app blijft bruikbaar."
        case .authorizedWhenInUse:
            return "Locatie is alleen tijdens gebruik toegestaan. Kies 'Altijd' in Instellingen voor automatische aanwezigheid op de achtergrond."
        case .denied:
            return "Locatietoegang is uitgeschakeld. Projecten blijven zichtbaar, maar automatische IN/UIT-waarnemingen worden niet gemaakt."
        case .restricted:
            return "Locatietoegang is op dit toestel beperkt. Projecten blijven zichtbaar, maar automatische aanwezigheid is niet beschikbaar."
        case .authorizedAlways:
            return "Automatische aanwezigheid is actief."
        @unknown default:
            return "Locatiestatus is onbekend. Projecten blijven zichtbaar; automatische aanwezigheid is mogelijk niet beschikbaar."
        }
    }

    private var locationActionTitle: String {
        locationMonitor.authorizationStatus == .notDetermined
            ? "Locatie inschakelen"
            : "Open Instellingen"
    }

    private func handleLocationAction() {
        if locationMonitor.authorizationStatus == .notDetermined {
            locationMonitor.requestAuthorization()
            return
        }
        guard let settingsURL = URL(string: UIApplication.openSettingsURLString) else { return }
        UIApplication.shared.open(settingsURL)
    }

    private var linkView: some View {
        VStack(alignment: .leading, spacing: 20) {
            Label("Eenmalig koppelen", systemImage: "iphone.gen3")
                .font(.title2.bold())

            Text("Open de persoonlijke activatielink die je via WhatsApp hebt ontvangen. Daarmee koppel je dit toestel aan Office.")
                .foregroundStyle(.secondary)
            if OnSiteConfiguration.isOfficeTest {
                Text("Office-test · gebruik de uitnodiging uit de testomgeving.")
                    .font(.caption)
            } else {
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
            }

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
