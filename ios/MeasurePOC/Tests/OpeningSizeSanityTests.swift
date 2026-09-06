import XCTest
@testable import BreboMeasurePOC

final class OpeningSizeSanityTests: XCTestCase {
    func testRejectsObviouslyWrongOpeningSizes() {
        XCTAssertFalse(OpeningSizeSanity.isPlausible(widthMm: 50, heightMm: 2000))
        XCTAssertFalse(OpeningSizeSanity.isPlausible(widthMm: 1200, heightMm: 9000))
        XCTAssertTrue(OpeningSizeSanity.isPlausible(widthMm: 1200, heightMm: 2200))
    }
}
