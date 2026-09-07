import ARKit
import RealityKit
import SwiftUI

struct LidarScanView: UIViewRepresentable {
    let isScanning: Bool
    let onProgress: (_ depthFrames: Int, _ meshAnchors: Int) -> Void
    let onMeshPoints: (_ points: [SIMD3<Float>]) -> Void

    init(
        isScanning: Bool,
        onProgress: @escaping (_ depthFrames: Int, _ meshAnchors: Int) -> Void,
        onMeshPoints: @escaping (_ points: [SIMD3<Float>]) -> Void = { _ in }
    ) {
        self.isScanning = isScanning
        self.onProgress = onProgress
        self.onMeshPoints = onMeshPoints
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(onProgress: onProgress, onMeshPoints: onMeshPoints)
    }

    func makeUIView(context: Context) -> ARView {
        let view = ARView(frame: .zero)
        context.coordinator.view = view
        view.session.delegate = context.coordinator

        let configuration = ARWorldTrackingConfiguration()
        configuration.worldAlignment = .gravity
        if ARWorldTrackingConfiguration.supportsSceneReconstruction(.mesh) {
            configuration.sceneReconstruction = .mesh
        }
        if ARWorldTrackingConfiguration.supportsFrameSemantics(.sceneDepth) {
            configuration.frameSemantics.insert(.sceneDepth)
        }
        view.session.run(configuration, options: [.resetTracking, .removeExistingAnchors])
        return view
    }

    func updateUIView(_ uiView: ARView, context: Context) {
        context.coordinator.isScanning = isScanning
    }

    final class Coordinator: NSObject, ARSessionDelegate {
        weak var view: ARView?
        var isScanning = false
        private var depthFrames = 0
        private let onProgress: (_ depthFrames: Int, _ meshAnchors: Int) -> Void
        private let onMeshPoints: (_ points: [SIMD3<Float>]) -> Void

        init(
            onProgress: @escaping (_ depthFrames: Int, _ meshAnchors: Int) -> Void,
            onMeshPoints: @escaping (_ points: [SIMD3<Float>]) -> Void
        ) {
            self.onProgress = onProgress
            self.onMeshPoints = onMeshPoints
        }

        func session(_ session: ARSession, didUpdate frame: ARFrame) {
            guard isScanning else { return }
            if frame.sceneDepth != nil {
                depthFrames += 1
            }
            let meshCount = frame.anchors.compactMap { $0 as? ARMeshAnchor }.count
            DispatchQueue.main.async { [depthFrames, onProgress] in
                onProgress(depthFrames, meshCount)
            }
        }

        func session(_ session: ARSession, didAdd anchors: [ARAnchor]) {
            collectMeshPoints(from: anchors)
        }

        func session(_ session: ARSession, didUpdate anchors: [ARAnchor]) {
            collectMeshPoints(from: anchors)
        }

        private func collectMeshPoints(from anchors: [ARAnchor]) {
            guard isScanning else { return }
            let meshAnchors = anchors.compactMap { $0 as? ARMeshAnchor }
            guard !meshAnchors.isEmpty else { return }

            var points: [SIMD3<Float>] = []
            points.reserveCapacity(meshAnchors.reduce(0) { $0 + $1.geometry.vertices.count })

            for anchor in meshAnchors {
                let vertices = anchor.geometry.vertices
                for index in 0..<vertices.count {
                    let local = vertices.vertex(at: UInt32(index))
                    let world4 = anchor.transform * SIMD4<Float>(local.x, local.y, local.z, 1)
                    points.append(SIMD3<Float>(world4.x, world4.y, world4.z))
                }
            }

            DispatchQueue.main.async { [onMeshPoints] in
                onMeshPoints(points)
            }
        }
    }
}

private extension ARGeometrySource {
    func vertex(at index: UInt32) -> SIMD3<Float> {
        let pointer = buffer.contents().advanced(by: offset + stride * Int(index))
        return pointer.assumingMemoryBound(to: SIMD3<Float>.self).pointee
    }
}
