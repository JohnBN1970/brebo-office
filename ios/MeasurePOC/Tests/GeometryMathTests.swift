import XCTest
@testable import BreboMeasurePOC

final class GeometryMathTests: XCTestCase {
    func testPointToPlaneDistanceInMillimetres() {
        let plane = DetectedPlane(
            normal: SIMD3<Float>(1, 0, 0),
            point: SIMD3<Float>(0, 0, 0),
            sampleCount: 100,
            residualMm: 1
        )
        let point = SIMD3<Float>(0.125, 2, 3)
        XCTAssertEqual(GeometryMath.distanceMillimetres(from: point, toPlane: plane), 125, accuracy: 0.01)
    }

    func testOppositeNormalsAreStillParallel() {
        let a = DetectedPlane(normal: SIMD3<Float>(1, 0, 0), point: .zero, sampleCount: 10, residualMm: 1)
        let b = DetectedPlane(normal: SIMD3<Float>(-1, 0, 0), point: .zero, sampleCount: 10, residualMm: 1)
        XCTAssertTrue(GeometryMath.areApproximatelyParallel(a, b))
    }
}
