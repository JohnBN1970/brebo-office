import Foundation

@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?
    @Published private(set) var pendingCount = 0
    @Published private(set) var registrationHistory: [CrewPresenceEvent] = []

    private let send: (CrewPresenceEvent, String) async throws -> Void
    private let defaults: UserDefaults
    private let deviceToken: () -> String?
    private let queueKey = "brebo.onsite.pending-presence.v1"
    private let historyKey = "brebo.onsite.registration-history.v1"
    private var pending: [CrewPresenceEvent] = []
    // Approval is Office-owned. Never infer it from successful upload or payment.
    private let approvalKey = "brebo.onsite.approved-presence.v1"
    @Published private(set) var approvedAtByEventId: [UUID: Date] = [:]
    private let approvedVisibilityDays = 5
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

    var activeRegistrations: [CrewPresenceEvent] {
        visibleRegistrations(asOf: Date())
    }

    func visibleRegistrations(asOf date: Date) -> [CrewPresenceEvent] {
        registrationHistory.filter { event in
            guard let approvedAt = approvedAtByEventId[event.id] else { return true }
            let expiry = Calendar(identifier: .gregorian).date(byAdding: .day, value: approvedVisibilityDays, to: approvedAt)
            return date < (expiry ?? approvedAt)
        }
    }

    // Call only with approval timestamps verified by Office, never local user input.
    func applyOfficeApprovals(_ approvals: [UUID: Date]) {
        approvedAtByEventId = approvals
        if let data = try? JSONEncoder().encode(approvals) {
            defaults.set(data, forKey: approvalKey)
        }
    }

    private func restore() {
        if let data = defaults.data(forKey: approvalKey),
           let approvals = try? JSONDecoder().decode([UUID: Date].self, from: data) {
            approvedAtByEventId = approvals
        }
        if let data = defaults.data(forKey: historyKey),
           let history = try? JSONDecoder().decode([CrewPresenceEvent].self, from: data) {
            registrationHistory = history
        }
        if let data = defaults.data(forKey: queueKey),
           let events = try? JSONDecoder().decode([CrewPresenceEvent].self, from: data) {
            pending = events
            pendingCount = events.count
            // Recover unsent events after upgrading an existing installation.
            let known = Set(registrationHistory.map(\.id))
            registrationHistory.append(contentsOf: events.filter { !known.contains($0.id) })
        }
        seenEventIds.formUnion(registrationHistory.map(\.id))
        seenEventIds.formUnion(pending.map(\.id))
        persistHistory()
    }

    private func persistHistory() {
        if let data = try? JSONEncoder().encode(registrationHistory) {
            defaults.set(data, forKey: historyKey)
        }
    }

    func isAwaitingUpload(_ id: UUID) -> Bool {
        pending.contains { $0.id == id }
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
        registrationHistory.append(event)
        persistHistory()
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
