import XCTest
@testable import BreboMeasurePOC

final class BuildIdentityTests: XCTestCase {
    func testBuildIsClearlyPOC() {
        XCTAssertEqual(BuildIdentity.product, "BREBO Measure")
        XCTAssertEqual(BuildIdentity.channel, "POC")
    }
}
