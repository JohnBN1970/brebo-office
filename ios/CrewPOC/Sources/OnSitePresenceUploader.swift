import Foundation

@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?
    @Published private(set) var pendingCount = 0

    private let send: (CrewPresenceEvent, String) async throws -> Void
    private let defaults: UserDefaults
    private let deviceToken: () -> String?
    private let queueKey = "brebo.onsite.pending-presence.v1"
    private var pending: [CrewPresenceEvent] = []
    private var uploading = false
    private var seenEventIds: Set<UUID> = []

    init(defaults: UserDefaults = .standard,
         deviceToken: @escaping () -> String? = OnSiteSecureStore.deviceToken,
         send: @escaping (CrewPresenceEvent, String) async throws -> Void = { event, token in
             try await OnSiteAPIClient().recordPresence(event: event, deviceToken: token)
         }) {
        self.defaults = defaults
        self.deviceToken = deviceToken
        self.send = send
        restore()
    }

    private func restore() {
        guard let data = defaults.data(forKey: queueKey),
              let events = try? JSONDecoder().decode([CrewPresenceEvent].self, from: data) else { return }
        pending = events
        seenEventIds.formUnion(events.map(\.id))
        pendingCount = events.count
    }

    private func persist() {
        if let data = try? JSONEncoder().encode(pending) {
            defaults.set(data, forKey: queueKey)
        }
        pendingCount = pending.count
    }

    func enqueue(_ event: CrewPresenceEvent) {
        guard seenEventIds.insert(event.id).inserted else { return }
        pending.append(event)
        persist()
    }

    func uploadNewEvents(_ events: [CrewPresenceEvent]) async {
        events.forEach(enqueue)
        await retryPending()
    }

    func retryPending() async {
        guard !uploading, let token = deviceToken() else { return }
        uploading = true
        defer { uploading = false }

        while !Task.isCancelled, token == deviceToken(), let event = pending.first {
            do {
                try await send(event, token)
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
