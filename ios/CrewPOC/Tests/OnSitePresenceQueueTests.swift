import XCTest
@testable import BreboCrewPOC

final class OnSitePresenceQueueTests: XCTestCase {
    func testRestartRetryAndAcknowledgement() {
        let name = "OnSiteQueueTests-" + UUID().uuidString
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let event = CrewPresenceEvent(projectId: "17", zoneId: "3", kind: .enteredProject)
        let queue = OnSitePresenceQueue(deviceToken: "worker-a", defaults: defaults)
        queue.enqueue([event, event])
        XCTAssertEqual(queue.events.count, 1)
        let restored = OnSitePresenceQueue(deviceToken: "worker-a", defaults: defaults)
        XCTAssertEqual(restored.events, [event]) // A failed upload never acknowledges.
        restored.acknowledge(event.id)
        XCTAssertTrue(OnSitePresenceQueue(deviceToken: "worker-a", defaults: defaults).events.isEmpty)
    }

    func testCredentialsNeverSharePendingEvidenceAndLogoutClearsIt() {
        let name = "OnSiteQueueTests-" + UUID().uuidString
        let defaults = UserDefaults(suiteName: name)!
        defer { defaults.removePersistentDomain(forName: name) }
        let queue = OnSitePresenceQueue(deviceToken: "worker-a", defaults: defaults)
        queue.enqueue([CrewPresenceEvent(projectId: "17", zoneId: "3", kind: .leftProject)])
        XCTAssertTrue(OnSitePresenceQueue(deviceToken: "worker-b", defaults: defaults).events.isEmpty)
        OnSitePresenceQueue.clear(deviceToken: "worker-a", defaults: defaults)
        XCTAssertTrue(OnSitePresenceQueue(deviceToken: "worker-a", defaults: defaults).events.isEmpty)
    }
}
