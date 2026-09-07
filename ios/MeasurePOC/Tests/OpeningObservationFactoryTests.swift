import XCTest
@testable import BreboMeasurePOC

final class OpeningObservationFactoryTests: XCTestCase {
    func testGeometryCreatesSixDetectedObservations() {
        let geometry = OpeningGeometry(
            widthTopMm: 1000, widthMiddleMm: 1001, widthBottomMm: 1002,
            heightLeftMm: 2000, heightMiddleMm: 2001, heightRightMm: 2002,
            confidence: 0.9, uncertaintyMm: 3
        )
        let observations = OpeningObservationFactory.make(from: geometry)
        XCTAssertEqual(observations.count, 6)
        XCTAssertTrue(observations.allSatisfy { $0.provenance == .detected })
        XCTAssertEqual(observations.first?.key, "opening.width.top")
    }
}
