import XCTest
@testable import BreboMeasurePOC

final class ScanCaptureMetadataTests: XCTestCase {
    func testCoordinateAndUnitConventionIsExplicit() {
        let metadata = ScanCaptureMetadata.current()
        XCTAssertEqual(metadata.coordinateSystem, "arkit_world_gravity_aligned")
        XCTAssertTrue(metadata.units.contains("millimetres_output"))
    }
}
