import XCTest
@testable import BreboMeasurePOC

final class OpeningGeometryFromBoundariesTests: XCTestCase {
    func testBuildsResearchGeometryFromRectangularBoundaries() throws {
        func plane(_ normal: SIMD3<Float>, _ point: SIMD3<Float>) -> DetectedPlane {
            DetectedPlane(normal: normal, point: point, sampleCount: 100, residualMm: 1)
        }
        let boundaries = OpeningBoundarySet(
            left: plane(SIMD3<Float>(1, 0, 0), .zero),
            right: plane(SIMD3<Float>(-1, 0, 0), SIMD3<Float>(1.1, 0, 0)),
            top: plane(SIMD3<Float>(0, 1, 0), SIMD3<Float>(0, 2.2, 0)),
            bottom: plane(SIMD3<Float>(0, -1, 0), .zero)
        )
        let geometry = try OpeningGeometryDetector().geometry(from: boundaries)
        XCTAssertEqual(geometry.nominalWidthMm, 1100, accuracy: 0.1)
        XCTAssertEqual(geometry.nominalHeightMm, 2200, accuracy: 0.1)
    }
}
