import Foundation

@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?
    @Published private(set) var pendingCount = 0

    private let apiClient = OnSiteAPIClient()
    private let queueKey = "brebo.onsite.pending-presence.v1"
    private var pending: [CrewPresenceEvent] = []
    private var uploading = false
    private var seenEventIds: Set<UUID> = []

    init() {
        restore()
    }

    private func restore() {
        guard let data = UserDefaults.standard.data(forKey: queueKey),
              let events = try? JSONDecoder().decode([CrewPresenceEvent].self, from: data) else { return }
        pending = events
        seenEventIds.formUnion(events.map(\.id))
        pendingCount = events.count
    }

    private func persist() {
        if let data = try? JSONEncoder().encode(pending) {
            UserDefaults.standard.set(data, forKey: queueKey)
        }
        pendingCount = pending.count
    }

    func uploadNewEvents(_ events: [CrewPresenceEvent]) async {
        let additions = events.filter { !seenEventIds.contains($0.id) }
        seenEventIds.formUnion(additions.map(\.id))
        if !additions.isEmpty {
            pending.append(contentsOf: additions)
            persist()
        }
        await retryPending()
    }

    func retryPending() async {
        guard !uploading, let token = OnSiteSecureStore.deviceToken() else { return }
        uploading = true
        defer { uploading = false }

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
}
