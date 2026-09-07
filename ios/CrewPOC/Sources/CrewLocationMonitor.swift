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
        stopAll()
        for zone in zones {
            let radius = min(zone.radiusMetres, manager.maximumRegionMonitoringDistance)
            let region = CLCircularRegion(center: zone.coordinate, radius: radius, identifier: zone.id)
            region.notifyOnEntry = true
            region.notifyOnExit = true
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
        events.append(CrewPresenceEvent(projectId: region.identifier, kind: .enteredProject))
    }

    func locationManager(_ manager: CLLocationManager, didExitRegion region: CLRegion) {
        states[region.identifier] = .outside
        events.append(CrewPresenceEvent(projectId: region.identifier, kind: .leftProject))
    }
}
