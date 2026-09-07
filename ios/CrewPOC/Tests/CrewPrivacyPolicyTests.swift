import XCTest
@testable import BreboCrewPOC

final class CrewPrivacyPolicyTests: XCTestCase {
    func testPOCDoesNotStoreContinuousLocationTrail() {
        XCTAssertFalse(CrewPrivacyPolicy.storesRouteHistory)
        XCTAssertFalse(CrewPrivacyPolicy.storesContinuousCoordinates)
        XCTAssertTrue(CrewPrivacyPolicy.storesPresenceEvents)
        XCTAssertEqual(CrewPrivacyPolicy.locationPurpose, "project_presence")
    }
}
