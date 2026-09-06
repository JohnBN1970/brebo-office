import XCTest
@testable import BreboMeasurePOC

final class ScanErrorTests: XCTestCase {
    func testUncertainGeometryRequestsRescan() {
        XCTAssertTrue(ScanError.geometryUncertain.message.lowercased().contains("opnieuw scannen"))
    }
}
