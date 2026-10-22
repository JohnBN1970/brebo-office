import Foundation
import Combine

@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?

    private let apiClient = OnSiteAPIClient()
    private var queue: OnSitePresenceQueue?
    private var queueToken: String?
    private var isFlushing = false

    func uploadNewEvents(_ events: [CrewPresenceEvent]) async {
        guard let token = OnSiteSecureStore.deviceToken() else { return }
        let pending = currentQueue(token)
        pending.enqueue(events)
        await flushPending()
    }

    func flushPending() async {
        guard !isFlushing, let token = OnSiteSecureStore.deviceToken() else { return }
        let pending = currentQueue(token)
        isFlushing = true
        defer { isFlushing = false }
        while let event = pending.events.first {
            guard OnSiteSecureStore.deviceToken() == token else { return }
            do {
                try await apiClient.recordPresence(event: event, deviceToken: token)
                guard OnSiteSecureStore.deviceToken() == token else { return }
                pending.acknowledge(event.id)
                lastUploadedEventId = event.id
                lastError = nil
            } catch {
                lastError = error.localizedDescription
                return
            }
        }
    }

    private func currentQueue(_ token: String) -> OnSitePresenceQueue {
        if queueToken != token || queue == nil {
            queue = OnSitePresenceQueue(deviceToken: token)
            queueToken = token
        }
        return queue!
    }
}
