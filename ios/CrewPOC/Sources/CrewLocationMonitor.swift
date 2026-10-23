import CoreLocation
import Foundation

@MainActor
final class CrewLocationMonitor: NSObject, ObservableObject, CLLocationManagerDelegate {
    @Published private(set) var authorizationStatus: CLAuthorizationStatus = .notDetermined
    @Published private(set) var states: [String: CrewPresenceState] = [:]
    @Published private(set) var events: [CrewPresenceEvent] = []

    private let manager = CLLocationManager()
    private var zonesByIdentifier: [String: CrewProjectZone] = [:]
    private var activeZoneIds: Set<String> = Set(UserDefaults.standard.stringArray(forKey: "brebo.onsite.active-zones.v1") ?? [])

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

    private func recordArrival(for regionId: String) {
        guard let zone = zonesByIdentifier[regionId] else { return }
        guard activeZoneIds.insert(regionId).inserted else { return }
        UserDefaults.standard.set(activeZoneIds.sorted(), forKey: "brebo.onsite.active-zones.v1")
        events.append(CrewPresenceEvent(projectId: zone.projectId, zoneId: zone.id, kind: .enteredProject))
    }

    private func recordDeparture(for regionId: String) {
        guard let zone = zonesByIdentifier[regionId] else { return }
        guard activeZoneIds.remove(regionId) != nil else { return }
        UserDefaults.standard.set(activeZoneIds.sorted(), forKey: "brebo.onsite.active-zones.v1")
        events.append(CrewPresenceEvent(projectId: zone.projectId, zoneId: zone.id, kind: .leftProject))
    }

    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        authorizationStatus = manager.authorizationStatus
    }

    func locationManager(_ manager: CLLocationManager, didDetermineState state: CLRegionState, for region: CLRegion) {
        switch state {
        case .inside:
            states[region.identifier] = .present
            // Start at the confirmed observation time, not an assumed earlier arrival.
            recordArrival(for: region.identifier)
        case .outside:
            states[region.identifier] = .outside
            recordDeparture(for: region.identifier)
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
