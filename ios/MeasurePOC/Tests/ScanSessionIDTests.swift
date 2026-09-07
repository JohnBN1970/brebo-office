import XCTest
@testable import BreboMeasurePOC

final class ScanSessionIDTests: XCTestCase {
    func testSessionIdIsStableForInstance() {
        let id = ScanSessionID()
        XCTAssertEqual(id.description, id.description)
        XCTAssertFalse(id.description.isEmpty)
    }
}
