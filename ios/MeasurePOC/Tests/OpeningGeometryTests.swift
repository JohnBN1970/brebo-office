import XCTest
@testable import BreboMeasurePOC

final class OpeningGeometryTests: XCTestCase {
    func testNominalDimensionsAverageThreeSections() {
        let geometry = OpeningGeometry(
            widthTopMm: 1000,
            widthMiddleMm: 1002,
            widthBottomMm: 1004,
            heightLeftMm: 2000,
            heightMiddleMm: 2003,
            heightRightMm: 2006,
            confidence: 0.95,
            uncertaintyMm: 2
        )
        XCTAssertEqual(geometry.nominalWidthMm, 1002, accuracy: 0.001)
        XCTAssertEqual(geometry.nominalHeightMm, 2003, accuracy: 0.001)
    }
}
