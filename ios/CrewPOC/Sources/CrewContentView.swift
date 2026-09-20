import SwiftUI

struct CrewContentView: View {
    @StateObject private var locationMonitor = CrewLocationMonitor()

    var body: some View {
        NavigationStack {
            Group {
                if locationMonitor.authorizationStatus == .authorizedAlways ||
                    locationMonitor.authorizationStatus == .authorizedWhenInUse {
                    operationalView
                } else {
                    permissionView
                }
            }
            .padding()
            .navigationTitle("OnSite")
        }
    }

    private var permissionView: some View {
        VStack(spacing: 20) {
            Image(systemName: "mappin.and.ellipse")
                .font(.system(size: 48))

            Text("BREBO OnSite")
                .font(.largeTitle.bold())

            Text("Aantoonbare aanwezigheid op toegewezen BREBO-projecten, zonder routehistorie.")
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

    private var operationalView: some View {
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
                    HStack {
                        Label("Nog te koppelen aan Office", systemImage: "person.crop.circle")
                        Spacer()
                    }
                    .frame(maxWidth: .infinity)
                }

                GroupBox("Toegewezen projecten") {
                    VStack(alignment: .leading, spacing: 8) {
                        Label("Nog geen Office-projecten geladen", systemImage: "building.2")
                        Text("De volgende bouwslag haalt hier de projectzones uit BREBO Inzet/Office op.")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }

                GroupBox("Aanwezigheid") {
                    VStack(alignment: .leading, spacing: 8) {
                        Label("Wacht op toegewezen projectzone", systemImage: "mappin.and.ellipse")
                        Text("IN/UIT wordt aanwezigheidsevidence en nog geen geboekte werktijd.")
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
