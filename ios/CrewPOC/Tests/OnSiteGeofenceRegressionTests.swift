import Foundation
import XCTest
@testable import CrewPOC

@MainActor
final class OnSiteGeofenceRegressionTests: XCTestCase {
    private func isolatedDefaults() -> UserDefaults {
        let suite = "brebo.onsite.regression." + UUID().uuidString
        let defaults = UserDefaults(suiteName: suite)!
        defaults.removePersistentDomain(forName: suite)
        return defaults
    }

    private func event(_ kind: CrewPresenceEventKind, id: UUID = UUID()) -> CrewPresenceEvent {
        CrewPresenceEvent(id: id, projectId: "test-project", zoneId: "test-zone",
                          kind: kind, occurredAt: Date(timeIntervalSince1970: 1_700_000_000))
    }

    func testOfflineEventsSurviveRestartAndUploadInOrder() async {
        let defaults = isolatedDefaults()
        let entered = event(.enteredProject)
        let left = event(.leftProject)
        let reentered = event(.enteredProject)
        let offline = OnSitePresenceUploader(defaults: defaults, deviceToken: { nil },
                                             send: { _, _ in XCTFail("Offline uploader must not send") })
        await offline.uploadNewEvents([entered, left, reentered])
        XCTAssertEqual(offline.pendingCount, 3)

        var sent: [UUID] = []
        let restored = OnSitePresenceUploader(defaults: defaults, deviceToken: { "test-token" },
                                              send: { item, _ in sent.append(item.id) })
        XCTAssertEqual(restored.pendingCount, 3)
        await restored.retryPending()
        XCTAssertEqual(sent, [entered.id, left.id, reentered.id])
        XCTAssertEqual(restored.pendingCount, 0)
    }

    func testDuplicateEventIdIsQueuedOnlyOnce() async {
        let defaults = isolatedDefaults()
        let entered = event(.enteredProject)
        let uploader = OnSitePresenceUploader(defaults: defaults, deviceToken: { nil },
                                              send: { _, _ in XCTFail("Must not send") })
        await uploader.uploadNewEvents([entered, entered])
        XCTAssertEqual(uploader.pendingCount, 1)
    }

    func testFailedUploadRetainsEventForRetry() async {
        enum NetworkFailure: Error { case offline }
        let defaults = isolatedDefaults()
        let entered = event(.enteredProject)
        let failing = OnSitePresenceUploader(defaults: defaults, deviceToken: { "test-token" },
                                             send: { _, _ in throw NetworkFailure.offline })
        await failing.uploadNewEvents([entered])
        XCTAssertEqual(failing.pendingCount, 1)

        var sent: [UUID] = []
        let recovered = OnSitePresenceUploader(defaults: defaults, deviceToken: { "test-token" },
                                               send: { item, _ in sent.append(item.id) })
        await recovered.retryPending()
        XCTAssertEqual(sent, [entered.id])
        XCTAssertEqual(recovered.pendingCount, 0)
    }
}
