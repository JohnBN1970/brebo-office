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
                        Label("Projecten worden automatisch door Office bepaald", systemImage: "building.2")
                        Text("OnSite kiest geen projecten en bevat geen handmatige projectinstellingen.")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }

                GroupBox("Aanwezigheid") {
                    VStack(alignment: .leading, spacing: 8) {
                        Label("Automatische registratie", systemImage: "mappin.and.ellipse")
                        Text("IN/UIT is aanwezigheidsevidence en nog geen geboekte werktijd.")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }

                GroupBox("Laatste waarneming") {
                    Text("Nog geen IN/UIT-waarneming")
                        .frame(maxWidth: .infinity, alignment: .leading)
                }
            }
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
