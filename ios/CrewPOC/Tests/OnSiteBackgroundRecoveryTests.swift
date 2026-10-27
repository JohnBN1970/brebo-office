import XCTest
import CoreLocation
@testable import BreboCrewPOC

@MainActor
final class OnSiteBackgroundRecoveryTests: XCTestCase {
    func testBootstrapCacheCannotCrossDeviceCredentials() throws {
        let name = "OnSiteCredentialCache.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let a = OnSiteBootstrap(employee: OnSiteBootstrapEmployee(id: "7", displayName: "A", language: "nl"), projects: [])
        let b = OnSiteBootstrap(employee: OnSiteBootstrapEmployee(id: "8", displayName: "B", language: "nl"), projects: [])
        defaults.set(try JSONEncoder().encode(a), forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "device-a"))
        var token: String? = "device-a"
        let identity = OnSiteIdentityStore(defaults: defaults, deviceToken: { token }, clearDeviceToken: { token = nil })
        XCTAssertEqual(identity.state, .linked(employeeId: "7", displayName: "A", language: .dutch))
        token = "device-b"
        identity.restoreCachedIdentity()
        XCTAssertEqual(identity.state, .unlinked)
        XCTAssertTrue(identity.assignedProjects.isEmpty)
        defaults.set(try JSONEncoder().encode(b), forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "device-b"))
        identity.restoreCachedIdentity()
        XCTAssertEqual(identity.state, .linked(employeeId: "8", displayName: "B", language: .dutch))
        XCTAssertFalse(OnSiteIdentityStore.bootstrapCacheKey(for: "device-a").contains("device-a"))
    }

    func testEmployeeSwitchIsolatesQueueHistoryAndApprovals() async {
        let name = "OnSiteOwnerTests.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let a = CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .enteredProject)
        let b = CrewPresenceEvent(projectId: "2", zoneId: "4", kind: .leftProject)
        var sent: [UUID] = []
        let uploader = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { "device" }, send: { event, _ in sent.append(event.id) })
        uploader.enqueue(a)
        uploader.applyOfficeApprovals([a.id: Date(timeIntervalSince1970: 1_700_000_000)])
        uploader.bindEmployee("8")
        XCTAssertTrue(uploader.registrationHistory.isEmpty)
        XCTAssertTrue(uploader.approvedAtByEventId.isEmpty)
        XCTAssertEqual(uploader.pendingCount, 0)
        uploader.enqueue(b)
        await uploader.retryPending()
        XCTAssertEqual(sent, [b.id])
        uploader.bindEmployee("7")
        XCTAssertEqual(uploader.registrationHistory, [a])
        XCTAssertEqual(uploader.pendingCount, 1)
        XCTAssertNotNil(uploader.approvedAtByEventId[a.id])
        uploader.bindEmployee(nil)
        await uploader.retryPending()
        XCTAssertTrue(uploader.registrationHistory.isEmpty)
        XCTAssertEqual(sent, [b.id])
    }

    func testInFlightOldUploadCannotConsumeNewEmployeeQueue() async {
        let name = "OnSiteOwnerRace.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let a = CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .enteredProject)
        let b = CrewPresenceEvent(projectId: "2", zoneId: "4", kind: .leftProject)
        var token = "device-a"
        var sent: [UUID] = []
        var uploader: OnSitePresenceUploader!
        uploader = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { token }, send: { event, _ in
            sent.append(event.id)
            if event.id == a.id {
                token = "device-b"
                uploader.bindEmployee("8")
                uploader.enqueue(b)
                await Task.yield()
            }
        })
        uploader.enqueue(a)
        await uploader.retryPending()
        XCTAssertEqual(uploader.registrationHistory, [b])
        XCTAssertEqual(uploader.pendingCount, 1)
        await uploader.retryPending()
        XCTAssertEqual(sent, [a.id, b.id])
        XCTAssertEqual(uploader.pendingCount, 0)
    }

    func testOmittedOfficeApprovalSnapshotRetainsKnownApproval() throws {
        let name = "OnSiteApprovalOmission.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let id = UUID()
        let employee = OnSiteBootstrapEmployee(id: "7", displayName: "Test", language: "nl")
        let approved = OnSiteBootstrap(employee: employee, projects: [], approvedEvents: [id.uuidString: Date(timeIntervalSince1970: 1_700_000_000)])
        defaults.set(try JSONEncoder().encode(approved), forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "device"))
        let identity = OnSiteIdentityStore(defaults: defaults, deviceToken: { "device" }, clearDeviceToken: {})
        XCTAssertNotNil(identity.officeApprovals?[id])
        defaults.set(try JSONEncoder().encode(OnSiteBootstrap(employee: employee, projects: [])), forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "device"))
        identity.restoreCachedIdentity()
        XCTAssertNotNil(identity.officeApprovals?[id])
        defaults.set(try JSONEncoder().encode(OnSiteBootstrap(employee: employee, projects: [], approvedEvents: [:])), forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "device"))
        identity.restoreCachedIdentity()
        XCTAssertTrue(identity.officeApprovals?.isEmpty == true)
    }

    func testNativeCallbackQueueSurvivesRelaunchAndRetriesSameEventOnce() async throws {
        let name = "OnSiteQueueTests.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let event = CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .enteredProject)
        var attempts: [UUID] = []
        let first = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { "device" }, send: { event, _ in
            attempts.append(event.id)
            throw URLError(.notConnectedToInternet)
        })
        first.enqueue(event)
        first.enqueue(event)
        XCTAssertEqual(first.pendingCount, 1)
        await first.retryPending()
        XCTAssertEqual(first.pendingCount, 1)

        let relaunched = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { "device" }, send: { event, _ in
            attempts.append(event.id)
        })
        XCTAssertEqual(relaunched.pendingCount, 1)
        await relaunched.retryPending()
        await relaunched.retryPending()
        XCTAssertEqual(attempts, [event.id, event.id])
        XCTAssertEqual(relaunched.pendingCount, 0)
    }

    func testOverlappingWakesDoNotUploadTwice() async {
        let name = "OnSiteQueueTests.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        var count = 0
        let uploader = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { "device" }, send: { _, _ in
            count += 1
            await Task.yield()
        })
        uploader.enqueue(CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .leftProject))
        async let first: Void = uploader.retryPending()
        async let second: Void = uploader.retryPending()
        _ = await (first, second)
        XCTAssertEqual(count, 1)
        XCTAssertEqual(uploader.pendingCount, 0)
    }

    func testCachedIdentityAndZonesRestoreWithoutServerAndResetClearsThem() throws {
        let name = "OnSiteIdentityTests.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let bootstrap = OnSiteBootstrap(
            employee: OnSiteBootstrapEmployee(id: "7", displayName: "Test", language: "nl"),
            projects: [OnSiteBootstrapProject(id: "1", name: "Project", zones: [
                OnSiteBootstrapZone(id: "3", name: "Zone", latitude: 51, longitude: 4, radiusMetres: 100)
            ])])
        defaults.set(try JSONEncoder().encode(bootstrap), forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "saved-device"))
        var token: String? = "saved-device"
        let identity = OnSiteIdentityStore(defaults: defaults, deviceToken: { token }, clearDeviceToken: { token = nil })
        XCTAssertEqual(identity.state, .linked(employeeId: "7", displayName: "Test", language: .dutch))
        XCTAssertEqual(identity.assignedProjects, bootstrap.projects)
        identity.reset()
        XCTAssertNil(token)
        XCTAssertEqual(identity.state, .unlinked)
        XCTAssertTrue(identity.assignedProjects.isEmpty)
        XCTAssertNil(defaults.data(forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "saved-device")))
    }

    func testCachedAssignmentsRequireDeviceCredential() throws {
        let name = "OnSiteIdentityTests.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let bootstrap = OnSiteBootstrap(employee: OnSiteBootstrapEmployee(id: "7", displayName: "Test", language: "nl"), projects: [])
        defaults.set(try JSONEncoder().encode(bootstrap), forKey: OnSiteIdentityStore.bootstrapCacheKey(for: "saved-device"))
        let identity = OnSiteIdentityStore(defaults: defaults, deviceToken: { nil }, clearDeviceToken: {})
        XCTAssertEqual(identity.state, .unlinked)
    }
    func testColdLocationCallbacksUseRestoredZonesAndSuppressDuplicateState() {
        let name = "OnSiteRegionTests.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let zone = CrewProjectZone(id: "3", projectId: "1", projectName: "Test",
                                   latitude: 51, longitude: 4, radiusMetres: 100)
        let region = CLCircularRegion(center: zone.coordinate, radius: 100, identifier: "3")
        var evidence: [CrewPresenceEvent] = []
        let first = CrewLocationMonitor(defaults: defaults)
        first.onEvent = { evidence.append($0) }
        first.monitor([zone])
        first.locationManager(CLLocationManager(), didEnterRegion: region)
        first.locationManager(CLLocationManager(), didDetermineState: .inside, for: region)
        XCTAssertEqual(evidence.map(\.kind), [.enteredProject])

        let restored = CrewLocationMonitor(defaults: defaults)
        restored.onEvent = { evidence.append($0) }
        restored.monitor([zone])
        restored.locationManager(CLLocationManager(), didDetermineState: .inside, for: region)
        restored.locationManager(CLLocationManager(), didExitRegion: region)
        restored.locationManager(CLLocationManager(), didExitRegion: region)
        XCTAssertEqual(evidence.map(\.kind), [.enteredProject, .leftProject])
        XCTAssertEqual(evidence.map(\.zoneId), ["3", "3"])
    }

    func testUploadedRegistrationRemainsInHistoryAfterRelaunch() async {
        let name = "OnSiteHistoryTests.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let event = CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .enteredProject)
        let first = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { "device" }, send: { _, _ in })
        first.enqueue(event)
        await first.retryPending()
        XCTAssertEqual(first.pendingCount, 0)
        XCTAssertEqual(first.registrationHistory, [event])
        XCTAssertFalse(first.isAwaitingUpload(event.id))
        let restored = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { "device" }, send: { _, _ in })
        XCTAssertEqual(restored.registrationHistory, [event])
        restored.enqueue(event)
        XCTAssertEqual(restored.registrationHistory.count, 1)
    }

    func testUnscopedLegacyQueueIsQuarantinedWithoutDataLoss() {
        let name = "OnSiteHistoryMigration.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let event = CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .leftProject)
        defaults.set(try! JSONEncoder().encode([event]), forKey: "brebo.onsite.pending-presence.v1")
        let uploader = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { nil }, send: { _, _ in })
        XCTAssertEqual(uploader.pendingCount, 0)
        XCTAssertTrue(uploader.registrationHistory.isEmpty)
        XCTAssertEqual(uploader.quarantinedLegacyCount, 1)
        XCTAssertNotNil(defaults.data(forKey: "brebo.onsite.pending-presence.v1"))
    }

    func testApprovedRegistrationRemainsVisibleForFiveDays() {
        let name = "OnSiteApprovalVisibility.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let event = CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .enteredProject)
        let uploader = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { nil }, send: { _, _ in })
        uploader.enqueue(event)
        let approved = Date(timeIntervalSince1970: 1_700_000_000)
        uploader.applyOfficeApprovals([event.id: approved])
        XCTAssertEqual(uploader.visibleRegistrations(asOf: approved.addingTimeInterval(5 * 86400 - 1)), [event])
        XCTAssertTrue(uploader.visibleRegistrations(asOf: approved.addingTimeInterval(5 * 86400)).isEmpty)
        XCTAssertEqual(uploader.registrationHistory, [event])
        let restored = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { nil }, send: { _, _ in })
        XCTAssertTrue(restored.visibleRegistrations(asOf: approved.addingTimeInterval(5 * 86400)).isEmpty)
    }

    func testUnapprovedRegistrationNeverExpiresLocally() {
        let name = "OnSiteUnapprovedVisibility.\(UUID())"
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let event = CrewPresenceEvent(projectId: "1", zoneId: "3", kind: .leftProject)
        let uploader = OnSitePresenceUploader(defaults: defaults, employeeId: "7", deviceToken: { nil }, send: { _, _ in })
        uploader.enqueue(event)
        XCTAssertEqual(uploader.visibleRegistrations(asOf: Date.distantFuture), [event])
    }

}
