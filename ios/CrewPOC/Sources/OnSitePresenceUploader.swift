import Foundation

/// Durable at-least-once upload queue for geofence evidence.
/// Acknowledged event IDs are removed only after the server accepts them.
@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?

    private let apiClient = OnSiteAPIClient()
    private let queueKey = "onsite.pending-presence-events.v1"
    private var pending: [CrewPresenceEvent] = []
    private var isFlushing = false

    init() {
        if let data = UserDefaults.standard.data(forKey: queueKey),
           let restored = try? JSONDecoder().decode([CrewPresenceEvent].self, from: data) {
            pending = restored
        }
    }

    func uploadNewEvents(_ events: [CrewPresenceEvent]) async {
        var existing = Set(pending.map(\.id))
        for event in events where existing.insert(event.id).inserted {
            pending.append(event)
        }
        persist()
        await flushPending()
    }

    func flushPending() async {
        guard !isFlushing, let token = OnSiteSecureStore.deviceToken() else { return }
        isFlushing = true
        defer { isFlushing = false }
        while let event = pending.first {
            do {
                try await apiClient.recordPresence(event: event, deviceToken: token)
                pending.removeFirst()
                persist()
                lastUploadedEventId = event.id
                lastError = nil
            } catch {
                lastError = error.localizedDescription
                return
            }
        }
    }

    private func persist() {
        guard let data = try? JSONEncoder().encode(pending) else { return }
        UserDefaults.standard.set(data, forKey: queueKey)
    }
}
