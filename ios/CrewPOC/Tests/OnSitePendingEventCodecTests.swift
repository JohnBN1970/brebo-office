import XCTest
@testable import BreboCrewPOC

final class OnSitePendingEventCodecTests: XCTestCase {
    func testPendingEventsSurviveSerializationWithStableIdentifiers() throws {
        let entered = CrewPresenceEvent(
            id: UUID(uuidString: "1D4C953A-16AB-46F3-B68A-93C25DF802E1")!,
            projectId: "1", zoneId: "3", kind: .enteredProject,
            occurredAt: Date(timeIntervalSince1970: 1_700_000_000)
        )
        let exited = CrewPresenceEvent(
            id: UUID(uuidString: "22BFCB3C-8CB8-44F5-BA8F-43EE12345678")!,
            projectId: "1", zoneId: "3", kind: .leftProject,
            occurredAt: Date(timeIntervalSince1970: 1_700_003_600)
        )
        let data = try JSONEncoder().encode([entered, exited])
        let restored = try JSONDecoder().decode([CrewPresenceEvent].self, from: data)
        XCTAssertEqual(restored, [entered, exited])
    }
}
