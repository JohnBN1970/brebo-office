import Foundation
import simd

struct AxisPlaneDetector {
    struct Configuration {
        let binSizeMetres: Float
        let minimumSamples: Int
        let maximumPlanesPerAxis: Int

        init(binSizeMetres: Float = 0.02, minimumSamples: Int = 50, maximumPlanesPerAxis: Int = 6) {
            self.binSizeMetres = binSizeMetres
            self.minimumSamples = minimumSamples
            self.maximumPlanesPerAxis = maximumPlanesPerAxis
        }
    }

    let configuration: Configuration

    init(configuration: Configuration = Configuration()) {
        self.configuration = configuration
    }

    func detect(from points: [SIMD3<Float>]) -> [DetectedPlane] {
        guard !points.isEmpty else { return [] }
        return detectAxis(points: points, axis: 0) +
            detectAxis(points: points, axis: 1) +
            detectAxis(points: points, axis: 2)
    }

    private func detectAxis(points: [SIMD3<Float>], axis: Int) -> [DetectedPlane] {
        var bins: [Int: [Float]] = [:]
        for point in points {
            let value = component(point, axis)
            let key = Int((value / configuration.binSizeMetres).rounded())
            bins[key, default: []].append(value)
        }

        let candidates = bins
            .filter { $0.value.count >= configuration.minimumSamples }
            .sorted { $0.value.count > $1.value.count }
            .prefix(configuration.maximumPlanesPerAxis)

        return candidates.map { _, values in
            let mean = values.reduce(0, +) / Float(values.count)
            let squared = values.reduce(Float(0)) { partial, value in
                let delta = value - mean
                return partial + delta * delta
            }
            let residualMm = Double(sqrt(squared / Float(values.count)) * 1000)
            var normal = SIMD3<Float>.zero
            normal[axis] = 1
            var point = SIMD3<Float>.zero
            point[axis] = mean
            return DetectedPlane(
                normal: normal,
                point: point,
                sampleCount: values.count,
                residualMm: residualMm
            )
        }
    }

    private func component(_ point: SIMD3<Float>, _ axis: Int) -> Float {
        switch axis {
        case 0: return point.x
        case 1: return point.y
        default: return point.z
        }
    }
}
