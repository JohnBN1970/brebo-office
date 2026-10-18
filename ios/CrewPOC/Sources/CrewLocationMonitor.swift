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
        // Do not tear down unchanged regions: re-registering can miss transitions.
        let desired = Dictionary(zones.prefix(20).map { ($0.id, $0) }, uniquingKeysWith: { first, _ in first })
        for region in manager.monitoredRegions where desired[region.identifier] == nil {
            manager.stopMonitoring(for: region)
            zonesByIdentifier.removeValue(forKey: region.identifier)
            states.removeValue(forKey: region.identifier)
        }
        for zone in desired.values {
            let radius = min(zone.radiusMetres, manager.maximumRegionMonitoringDistance)
            let region = CLCircularRegion(center: zone.coordinate, radius: radius, identifier: zone.id)
            region.notifyOnEntry = true
            region.notifyOnExit = true
            let existing = manager.monitoredRegions.first(where: { $0.identifier == zone.id }) as? CLCircularRegion
            if let existing, existing.center.latitude == region.center.latitude,
               existing.center.longitude == region.center.longitude,
               existing.radius == region.radius {
                zonesByIdentifier[zone.id] = zone
                continue
            }
            if let existing { manager.stopMonitoring(for: existing) }
            zonesByIdentifier[zone.id] = zone
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
