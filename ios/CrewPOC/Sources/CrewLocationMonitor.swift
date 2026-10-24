import CoreLocation
import Foundation

@MainActor
final class CrewLocationMonitor: NSObject, ObservableObject, CLLocationManagerDelegate {
    @Published private(set) var authorizationStatus: CLAuthorizationStatus = .notDetermined
    @Published private(set) var states: [String: CrewPresenceState] = [:]
    @Published private(set) var events: [CrewPresenceEvent] = []

    // Called synchronously before returning from the native location callback.
    var onEvent: ((CrewPresenceEvent) -> Void)?
    var onAuthorizationChanged: (() -> Void)?
    var onWake: (() -> Void)?

    private let manager = CLLocationManager()
    private var zonesByIdentifier: [String: CrewProjectZone] = [:]
    private let defaults: UserDefaults
    private var activeZoneIds: Set<String>

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        activeZoneIds = Set(defaults.stringArray(forKey: "brebo.onsite.active-zones.v1") ?? [])
        super.init()
        manager.delegate = self
        authorizationStatus = manager.authorizationStatus
    }

    func requestAuthorization() {
        manager.requestAlwaysAuthorization()
    }

    func monitor(_ zones: [CrewProjectZone]) {
        let desired = Dictionary(zones.prefix(20).map { ($0.id, $0) }, uniquingKeysWith: { first, _ in first })
        // Do not synthesize departures when Office changes a zone's assignment.
        // A changed geometry needs a fresh native state determination.
        let changedIds = Set(desired.compactMap { id, zone -> String? in
            guard let previous = zonesByIdentifier[id] else { return nil }
            return previous != zone ? id : nil
        })
        activeZoneIds.subtract(changedIds)
        zonesByIdentifier = desired
        let desiredIds = Set(desired.keys)
        activeZoneIds.formIntersection(desiredIds)
        defaults.set(activeZoneIds.sorted(), forKey: "brebo.onsite.active-zones.v1")
        states = states.filter { desiredIds.contains($0.key) && !changedIds.contains($0.key) }
        guard authorizationStatus == .authorizedAlways,
              CLLocationManager.isMonitoringAvailable(for: CLCircularRegion.self) else { return }

        let registered = manager.monitoredRegions
        for region in registered where desired[region.identifier] == nil {
            manager.stopMonitoring(for: region)
        }
        for zone in desired.values {
            let radius = min(zone.radiusMetres, manager.maximumRegionMonitoringDistance)
            let region = CLCircularRegion(center: zone.coordinate, radius: radius, identifier: zone.id)
            region.notifyOnEntry = true
            region.notifyOnExit = true
            let previous = registered.first { $0.identifier == zone.id } as? CLCircularRegion
            if previous?.center.latitude != region.center.latitude ||
                previous?.center.longitude != region.center.longitude || previous?.radius != radius {
                if let previous { manager.stopMonitoring(for: previous) }
                manager.startMonitoring(for: region)
            }
            manager.requestState(for: region)
        }
    }

    func stopAll() {
        manager.monitoredRegions.forEach(manager.stopMonitoring(for:))
        zonesByIdentifier.removeAll()
        states.removeAll()
        activeZoneIds.removeAll()
        defaults.removeObject(forKey: "brebo.onsite.active-zones.v1")
    }

    private func recordArrival(for regionId: String) {
        guard let zone = zonesByIdentifier[regionId] else { return }
        guard activeZoneIds.insert(regionId).inserted else { return }
        defaults.set(activeZoneIds.sorted(), forKey: "brebo.onsite.active-zones.v1")
        record(CrewPresenceEvent(projectId: zone.projectId, zoneId: zone.id, kind: .enteredProject))
    }

    private func recordDeparture(for regionId: String) {
        guard let zone = zonesByIdentifier[regionId] else { return }
        guard activeZoneIds.remove(regionId) != nil else { return }
        defaults.set(activeZoneIds.sorted(), forKey: "brebo.onsite.active-zones.v1")
        record(CrewPresenceEvent(projectId: zone.projectId, zoneId: zone.id, kind: .leftProject))
    }

    private func record(_ event: CrewPresenceEvent) {
        onEvent?(event)
        events.append(event)
    }

    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        authorizationStatus = manager.authorizationStatus
        if authorizationStatus == .denied || authorizationStatus == .restricted { stopAll() }
        onAuthorizationChanged?()
    }

    func locationManager(_ manager: CLLocationManager, didStartMonitoringFor region: CLRegion) {
        manager.requestState(for: region)
    }

    func locationManager(_ manager: CLLocationManager, didDetermineState state: CLRegionState, for region: CLRegion) {
        guard zonesByIdentifier[region.identifier] != nil else { return }
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
        onWake?()
    }

    func locationManager(_ manager: CLLocationManager, didEnterRegion region: CLRegion) {
        guard zonesByIdentifier[region.identifier] != nil else { return }
        states[region.identifier] = .present
        recordArrival(for: region.identifier)
        onWake?()
    }

    func locationManager(_ manager: CLLocationManager, didExitRegion region: CLRegion) {
        guard zonesByIdentifier[region.identifier] != nil else { return }
        states[region.identifier] = .outside
        recordDeparture(for: region.identifier)
        onWake?()
    }
}
