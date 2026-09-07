import ARKit
import RealityKit
import SwiftUI

struct LidarMeasureView: UIViewRepresentable {
    let resetToken: UUID
    let onMeasurementChanged: (_ pointCount: Int, _ distanceMillimetres: Double?) -> Void

    func makeCoordinator() -> Coordinator {
        Coordinator(onMeasurementChanged: onMeasurementChanged)
    }

    func makeUIView(context: Context) -> ARView {
        let view = ARView(frame: .zero)
        context.coordinator.view = view

        let configuration = ARWorldTrackingConfiguration()
        configuration.worldAlignment = .gravity

        if ARWorldTrackingConfiguration.supportsSceneReconstruction(.mesh) {
            configuration.sceneReconstruction = .mesh
        }
        if ARWorldTrackingConfiguration.supportsFrameSemantics(.sceneDepth) {
            configuration.frameSemantics.insert(.sceneDepth)
        }

        view.session.run(configuration, options: [.resetTracking, .removeExistingAnchors])

        let tap = UITapGestureRecognizer(target: context.coordinator, action: #selector(Coordinator.didTap(_:)))
        view.addGestureRecognizer(tap)

        return view
    }

    func updateUIView(_ uiView: ARView, context: Context) {
        if context.coordinator.lastResetToken != resetToken {
            context.coordinator.lastResetToken = resetToken
            context.coordinator.reset()
        }
    }

    final class Coordinator: NSObject {
        weak var view: ARView?
        var lastResetToken: UUID?
        private var points: [SIMD3<Float>] = []
        private let onMeasurementChanged: (_ pointCount: Int, _ distanceMillimetres: Double?) -> Void

        init(onMeasurementChanged: @escaping (_ pointCount: Int, _ distanceMillimetres: Double?) -> Void) {
            self.onMeasurementChanged = onMeasurementChanged
        }

        @objc func didTap(_ recognizer: UITapGestureRecognizer) {
            guard let view, points.count < 2 else { return }
            let location = recognizer.location(in: view)

            guard let result = view.raycast(from: location, allowing: .estimatedPlane, alignment: .any).first else {
                return
            }

            let transform = result.worldTransform
            let point = SIMD3<Float>(transform.columns.3.x, transform.columns.3.y, transform.columns.3.z)
            points.append(point)

            if points.count == 2 {
                let metres = simd_distance(points[0], points[1])
                onMeasurementChanged(2, Double(metres * 1000.0))
            } else {
                onMeasurementChanged(points.count, nil)
            }
        }

        func reset() {
            points.removeAll(keepingCapacity: true)
            onMeasurementChanged(0, nil)
        }
    }
}
