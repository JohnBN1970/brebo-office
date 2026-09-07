import XCTest
@testable import BreboMeasurePOC

final class OpeningShapeTests: XCTestCase {
    func testPOCOnlyPromisesRectangularOpening() {
        XCTAssertEqual(OpeningShape.pocSupported, .rectangular)
    }
}
