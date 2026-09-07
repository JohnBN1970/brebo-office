import SwiftUI

struct CrewContentView: View {
    @StateObject private var locationMonitor = CrewLocationMonitor()

    var body: some View {
        NavigationStack {
            VStack(spacing: 20) {
                Image(systemName: "person.2.badge.gearshape")
                    .font(.system(size: 48))

                Text("BREBO Crew")
                    .font(.largeTitle.bold())

                Text("Aanwezigheid op toegewezen projecten, zonder routehistorie.")
                    .multilineTextAlignment(.center)

                VStack(alignment: .leading, spacing: 8) {
                    Label("Geen continue GPS-route", systemImage: "location.slash")
                    Label("Binnen/buiten projectzone", systemImage: "mappin.and.ellipse")
                    Label("Gebeurtenissen vormen urenvoorstel", systemImage: "clock.arrow.circlepath")
                }
                .frame(maxWidth: .infinity, alignment: .leading)

                Button("Locatietoegang voorbereiden") {
                    locationMonitor.requestAuthorization()
                }
                .buttonStyle(.borderedProminent)

                Text("Status: \(authorizationText)")
                    .font(.caption)

                Spacer()

                Text("POC - geen routeopslag, geen automatische Office-upload")
                    .font(.caption2)
                    .foregroundStyle(.secondary)
            }
            .padding()
            .navigationTitle("Crew")
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
