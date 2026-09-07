import XCTest
@testable import BreboCrewPOC

final class OnSitePresenceSessionBuilderTests: XCTestCase {
    func testEntryAndExitBecomeOnePresenceSession() {
        let enteredAt = Date(timeIntervalSince1970: 1_000)
        let leftAt = Date(timeIntervalSince1970: 4_600)
        let entry = OnSitePresenceEvidence(
            projectId: "project-1",
            employeeId: "employee-1",
            kind: .enteredProject,
            occurredAt: enteredAt,
            deviceEventId: UUID()
        )
        let exit = OnSitePresenceEvidence(
            projectId: "project-1",
            employeeId: "employee-1",
            kind: .leftProject,
            occurredAt: leftAt,
            deviceEventId: UUID()
        )

        let sessions = OnSitePresenceSessionBuilder().build(from: [exit, entry])

        XCTAssertEqual(sessions.count, 1)
        XCTAssertEqual(sessions[0].projectId, "project-1")
        XCTAssertEqual(sessions[0].employeeId, "employee-1")
        XCTAssertEqual(sessions[0].durationSeconds, 3_600, accuracy: 0.001)
    }

    func testUnmatchedEntryStaysEvidenceAndDoesNotBecomeHours() {
        let entry = OnSitePresenceEvidence(
            projectId: "project-1",
            employeeId: "employee-1",
            kind: .enteredProject,
            occurredAt: Date(),
            deviceEventId: UUID()
        )

        XCTAssertTrue(OnSitePresenceSessionBuilder().build(from: [entry]).isEmpty)
    }
}
