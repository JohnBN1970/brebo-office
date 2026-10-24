#if DEBUG
import SwiftUI

/// Explicit debug-only UI fixture; never changes the real device credential or zones.
enum OnSiteMenuPreview {
    static var workspace: some View {
        let now = Date()
        let zone = OnSiteBootstrapZone(id: "preview-zone", name: "Werkterrein",
                                       latitude: 51.9, longitude: 4.5, radiusMetres: 100)
        let project = OnSiteBootstrapProject(id: "preview-project", name: "Voorbeeldproject", zones: [zone])
        return OnSiteWorkspaceView(
            employeeId: "preview", displayName: "Alex", language: "nl",
            projects: [project], states: [zone.id: .present], events: [
                CrewPresenceEvent(projectId: project.id, zoneId: zone.id, kind: .enteredProject, occurredAt: now)
            ], automaticPresence: true, locationDescription: "Altijd toegestaan",
            locationGuidance: "Automatische aanwezigheid is actief.", locationActionTitle: "Open Instellingen",
            pendingCount: 0, syncError: nil, connectionError: nil, locationAction: {})
            .tint(Color(red: 0.38, green: 0.17, blue: 0.49))
    }
}
#endif
