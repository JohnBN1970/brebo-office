#if DEBUG
import SwiftUI

/// Explicit debug-only UI fixture; never changes the real device credential or zones.
enum OnSiteMenuPreview {
    @MainActor
    static var restoredHistoryWorkspace: some View {
        let suite = "nl.brebo.onsite.history-ui-fixture"
        let defaults = UserDefaults(suiteName: suite)!
        if ProcessInfo.processInfo.arguments.contains("--reset-history-fixture") {
            defaults.removePersistentDomain(forName: suite)
            let event = CrewPresenceEvent(
                id: UUID(uuidString: "D02FC6D8-BC5E-41B1-A18B-D6FFB2D98001")!,
                projectId: "preview-project", zoneId: "preview-zone", kind: .enteredProject,
                occurredAt: Calendar.current.date(byAdding: .day, value: -1, to: Date())!)
            // An empty queue represents successful upload; history must remain.
            defaults.set(try! JSONEncoder().encode([event]), forKey: OnSitePresenceUploader.storageKey("registration-history", employeeId: "preview"))
        }
        let uploader = OnSitePresenceUploader(defaults: defaults, employeeId: "preview", deviceToken: { nil }, send: { _, _ in })
        let zone = OnSiteBootstrapZone(id: "preview-zone", name: "Werkterrein",
                                       latitude: 51.9, longitude: 4.5, radiusMetres: 100)
        let project = OnSiteBootstrapProject(id: "preview-project", name: "Voorbeeldproject", zones: [zone])
        return OnSiteWorkspaceView(
            employeeId: "preview", displayName: "Alex", language: "nl", projects: [project],
            states: [:], events: uploader.activeRegistrations,
            pendingEventIds: uploader.pendingEventIds,
            approvedEventIds: Set(uploader.approvedAtByEventId.keys),
            automaticPresence: true, locationDescription: "Altijd toegestaan",
            locationGuidance: "Automatische aanwezigheid is actief.", locationActionTitle: "Open Instellingen",
            pendingCount: uploader.pendingCount, syncError: nil, connectionError: nil, locationAction: {})
    }

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
