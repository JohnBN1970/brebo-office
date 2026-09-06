import XCTest
@testable import BreboMeasurePOC

final class ScanBuildNotesTests: XCTestCase {
    func testDisplayIdentifiesMeasurePOC() {
        XCTAssertTrue(ScanBuildNotes.display.contains("BREBO Measure"))
        XCTAssertTrue(ScanBuildNotes.display.contains("POC"))
    }
}
