import XCTest
@testable import BreboMeasurePOC

final class RectangularOpeningSelectorTests: XCTestCase {
    func testSelectsHorizontalAndVerticalBoundaryPairs() {
        func plane(normal: SIMD3<Float>, point: SIMD3<Float>, samples: Int = 200, residual: Double = 2) -> DetectedPlane {
            DetectedPlane(normal: normal, point: point, sampleCount: samples, residualMm: residual)
        }

        let planes = [
            plane(normal: SIMD3<Float>(1, 0, 0), point: SIMD3<Float>(0.0, 0, 0)),
            plane(normal: SIMD3<Float>(1, 0, 0), point: SIMD3<Float>(1.2, 0, 0)),
            plane(normal: SIMD3<Float>(0, 1, 0), point: SIMD3<Float>(0, 0.0, 0)),
            plane(normal: SIMD3<Float>(0, 1, 0), point: SIMD3<Float>(0, 2.1, 0)),
            plane(normal: SIMD3<Float>(0, 0, 1), point: SIMD3<Float>(0, 0, 0.1), samples: 60, residual: 8),
            plane(normal: SIMD3<Float>(0, 0, 1), point: SIMD3<Float>(0, 0, 0.5), samples: 60, residual: 8),
        ]

        let selection = RectangularOpeningSelector().select(from: planes)
        XCTAssertNotNil(selection)
        XCTAssertEqual(selection?.candidate.widthMm ?? 0, 1200, accuracy: 0.1)
        XCTAssertEqual(selection?.candidate.heightMm ?? 0, 2100, accuracy: 0.1)
    }

    func testRejectsMissingTopBottomPair() {
        func plane(normal: SIMD3<Float>, point: SIMD3<Float>) -> DetectedPlane {
            DetectedPlane(normal: normal, point: point, sampleCount: 200, residualMm: 2)
        }
        let planes = [
            plane(normal: SIMD3<Float>(1, 0, 0), point: SIMD3<Float>(0, 0, 0)),
            plane(normal: SIMD3<Float>(1, 0, 0), point: SIMD3<Float>(1.0, 0, 0)),
        ]
        XCTAssertNil(RectangularOpeningSelector().select(from: planes))
    }
}
