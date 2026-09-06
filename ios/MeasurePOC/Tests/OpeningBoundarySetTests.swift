import XCTest
@testable import BreboMeasurePOC

final class OpeningBoundarySetTests: XCTestCase {
    func testOppositeOpeningBoundariesMustBeParallel() {
        func plane(_ normal: SIMD3<Float>) -> DetectedPlane {
            DetectedPlane(normal: normal, point: .zero, sampleCount: 100, residualMm: 1)
        }
        let boundaries = OpeningBoundarySet(
            left: plane(SIMD3<Float>(1, 0, 0)),
            right: plane(SIMD3<Float>(-1, 0, 0)),
            top: plane(SIMD3<Float>(0, 1, 0)),
            bottom: plane(SIMD3<Float>(0, -1, 0))
        )
        XCTAssertTrue(boundaries.hasParallelOpposites)
    }
}
