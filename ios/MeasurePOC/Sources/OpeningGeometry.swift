import Foundation
import simd

struct OpeningGeometry: Codable, Equatable {
    let widthTopMm: Double
    let widthMiddleMm: Double
    let widthBottomMm: Double
    let heightLeftMm: Double
    let heightMiddleMm: Double
    let heightRightMm: Double
    let confidence: Double
    let uncertaintyMm: Double

    var nominalWidthMm: Double {
        (widthTopMm + widthMiddleMm + widthBottomMm) / 3.0
    }

    var nominalHeightMm: Double {
        (heightLeftMm + heightMiddleMm + heightRightMm) / 3.0
    }
}

struct DetectedPlane {
    let normal: SIMD3<Float>
    let point: SIMD3<Float>
    let sampleCount: Int
    let residualMm: Double
}
