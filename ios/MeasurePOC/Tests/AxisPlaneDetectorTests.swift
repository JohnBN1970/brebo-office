import XCTest
@testable import BreboMeasurePOC

final class AxisPlaneDetectorTests: XCTestCase {
    func testFindsDenseVerticalBoundaryPlanes() {
        var points: [SIMD3<Float>] = []
        for i in 0..<100 {
            let y = Float(i) * 0.01
            points.append(SIMD3<Float>(0.0, y, 0.3))
            points.append(SIMD3<Float>(1.2, y, 0.3))
        }

        let detector = AxisPlaneDetector(configuration: .init(binSizeMetres: 0.01, minimumSamples: 50, maximumPlanesPerAxis: 4))
        let planes = detector.detect(from: points)
        let xPlanes = planes.filter { abs($0.normal.x) > 0.9 }

        XCTAssertGreaterThanOrEqual(xPlanes.count, 2)
        XCTAssertTrue(xPlanes.contains { abs($0.point.x - 0.0) < 0.01 })
        XCTAssertTrue(xPlanes.contains { abs($0.point.x - 1.2) < 0.01 })
    }
}
