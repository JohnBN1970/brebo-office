import SwiftUI

struct CrewContentView: View {
    @StateObject private var locationMonitor = CrewLocationMonitor()

    var body: some View {
        NavigationStack {
            VStack(spacing: 20) {
                Image(systemName: "mappin.and.ellipse")
                    .font(.system(size: 48))

                Text("BREBO OnSite")
                    .font(.largeTitle.bold())

                Text("Aantoonbare aanwezigheid op toegewezen BREBO-projecten, zonder routehistorie.")
                    .multilineTextAlignment(.center)

                VStack(alignment: .leading, spacing: 8) {
                    Label("Geen continue GPS-route", systemImage: "location.slash")
                    Label("Binnen/buiten projectzone", systemImage: "mappin.and.ellipse")
                    Label("IN/UIT-waarnemingen direct aantoonbaar", systemImage: "checkmark.seal")
                    Label("Aanwezigheid is nog geen geboekte werktijd", systemImage: "clock.badge.questionmark")
                }
                .frame(maxWidth: .infinity, alignment: .leading)

                Button("Locatietoegang voorbereiden") {
                    locationMonitor.requestAuthorization()
                }
                .buttonStyle(.borderedProminent)

                Text("Status: \(authorizationText)")
                    .font(.caption)

                Spacer()

                Text("POC - aanwezigheidsevidence, geen routeopslag, nog geen automatische Office-upload")
                    .font(.caption2)
                    .foregroundStyle(.secondary)
            }
            .padding()
            .navigationTitle("OnSite")
        }
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
