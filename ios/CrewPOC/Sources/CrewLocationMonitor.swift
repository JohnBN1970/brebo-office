import CoreLocation
import Foundation

@MainActor
final class CrewLocationMonitor: NSObject, ObservableObject, CLLocationManagerDelegate {
    @Published private(set) var authorizationStatus: CLAuthorizationStatus = .notDetermined
    @Published private(set) var states: [String: CrewPresenceState] = [:]
    @Published private(set) var events: [CrewPresenceEvent] = []

    private let manager = CLLocationManager()
    private var zonesByIdentifier: [String: CrewProjectZone] = [:]

    override init() {
        super.init()
        manager.delegate = self
        authorizationStatus = manager.authorizationStatus
    }

    func requestAuthorization() {
        manager.requestAlwaysAuthorization()
    }

    func monitor(_ zones: [CrewProjectZone]) {
        // Reconcile instead of tearing down every monitored region on a UI refresh.
        // Restarting region monitoring can miss real boundary transitions.
        let next = Dictionary(zones.map { ($0.id, $0) }, uniquingKeysWith: { _, latest in latest })
        for region in manager.monitoredRegions where next[region.identifier] == nil {
            manager.stopMonitoring(for: region)
            zonesByIdentifier.removeValue(forKey: region.identifier)
            states.removeValue(forKey: region.identifier)
        }
        for zone in next.values {
            let radius = min(zone.radiusMetres, manager.maximumRegionMonitoringDistance)
            guard radius > 0 else { continue }
            let previous = zonesByIdentifier[zone.id]
            zonesByIdentifier[zone.id] = zone
            if previous == zone { continue }
            if let existing = manager.monitoredRegions.first(where: { $0.identifier == zone.id }) {
                manager.stopMonitoring(for: existing)
            }
            let region = CLCircularRegion(center: zone.coordinate, radius: radius, identifier: zone.id)
            region.notifyOnEntry = true
            region.notifyOnExit = true
            states[zone.id] = .unknown
            manager.startMonitoring(for: region)
            manager.requestState(for: region)
        }
    }

    func stopAll() {
        manager.monitoredRegions.forEach(manager.stopMonitoring(for:))
        zonesByIdentifier.removeAll()
        states.removeAll()
    }

    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        authorizationStatus = manager.authorizationStatus
    }

    func locationManager(_ manager: CLLocationManager, didDetermineState state: CLRegionState, for region: CLRegion) {
        switch state {
        case .inside: states[region.identifier] = .present
        case .outside: states[region.identifier] = .outside
        case .unknown: states[region.identifier] = .unknown
        @unknown default: states[region.identifier] = .unknown
        }
    }

    func locationManager(_ manager: CLLocationManager, didEnterRegion region: CLRegion) {
        states[region.identifier] = .present
        guard let zone = zonesByIdentifier[region.identifier] else { return }
        events.append(CrewPresenceEvent(
            projectId: zone.projectId,
            zoneId: zone.id,
            kind: .enteredProject
        ))
    }

    func locationManager(_ manager: CLLocationManager, didExitRegion region: CLRegion) {
        states[region.identifier] = .outside
        guard let zone = zonesByIdentifier[region.identifier] else { return }
        events.append(CrewPresenceEvent(
            projectId: zone.projectId,
            zoneId: zone.id,
            kind: .leftProject
        ))
    }
}
