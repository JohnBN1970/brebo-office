import Foundation

@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?
    @Published private(set) var pendingCount = 0
    @Published private(set) var registrationHistory: [CrewPresenceEvent] = []
    @Published private(set) var approvedAtByEventId: [UUID: Date] = [:]
    @Published private(set) var quarantinedLegacyCount = 0

    private let send: (CrewPresenceEvent, String) async throws -> Void
    private let defaults: UserDefaults
    private let deviceToken: () -> String?
    private var employeeId: String?
    private var generation = 0
    private var pending: [CrewPresenceEvent] = []
    private var seenEventIds: Set<UUID> = []
    private var uploading = false

    static func storageKey(_ name: String, employeeId: String) -> String {
        "brebo.onsite.\(name).v2.employee.\(Data(employeeId.utf8).base64EncodedString())"
    }

    init(defaults: UserDefaults = .standard, employeeId: String? = nil,
         deviceToken: @escaping () -> String? = OnSiteSecureStore.deviceToken,
         send: @escaping (CrewPresenceEvent, String) async throws -> Void = { event, token in
             try await OnSiteAPIClient().recordPresence(event: event, deviceToken: token)
         }) {
        self.defaults = defaults
        self.deviceToken = deviceToken
        self.send = send
        // Old unscoped records cannot prove their employee. Preserve, never resend
        // them under a newly linked person's credential.
        let legacy = ["brebo.onsite.pending-presence.v1", "brebo.onsite.registration-history.v1"]
            .compactMap { defaults.data(forKey: $0) }
            .compactMap { try? JSONDecoder().decode([CrewPresenceEvent].self, from: $0) }
            .flatMap { $0 }
        quarantinedLegacyCount = Set(legacy.map(\.id)).count
        bindEmployee(employeeId)
    }

    func bindEmployee(_ id: String?) {
        let next = id.flatMap { !$0.isEmpty && $0.utf8.count <= 128 ? $0 : nil }
        guard next != employeeId else { return }
        generation += 1
        employeeId = next
        pending = []; registrationHistory = []; approvedAtByEventId = [:]
        seenEventIds = []; pendingCount = 0; lastUploadedEventId = nil; lastError = nil
        guard let next else { return }
        func decode<T: Decodable>(_ name: String, as type: T.Type) -> T? {
            guard let data = defaults.data(forKey: Self.storageKey(name, employeeId: next)) else { return nil }
            return try? JSONDecoder().decode(type, from: data)
        }
        pending = decode("pending-presence", as: [CrewPresenceEvent].self) ?? []
        registrationHistory = decode("registration-history", as: [CrewPresenceEvent].self) ?? []
        approvedAtByEventId = decode("approved-presence", as: [UUID: Date].self) ?? [:]
        var known = Set(registrationHistory.map(\.id))
        for event in pending where known.insert(event.id).inserted { registrationHistory.append(event) }
        seenEventIds = known
        pendingCount = pending.count
    }

    var pendingEventIds: Set<UUID> { Set(pending.map(\.id)) }
    var activeRegistrations: [CrewPresenceEvent] { visibleRegistrations(asOf: Date()) }
    func visibleRegistrations(asOf date: Date) -> [CrewPresenceEvent] {
        registrationHistory.filter { event in
            guard let approvedAt = approvedAtByEventId[event.id] else { return true }
            return date < approvedAt.addingTimeInterval(5 * 86400)
        }
    }

    func applyOfficeApprovals(_ approvals: [UUID: Date]) {
        guard employeeId != nil else { return }
        let known = Set(registrationHistory.map(\.id))
        approvedAtByEventId = approvals.filter { known.contains($0.key) && $0.value <= Date() }
        persist()
    }

    private func persist() {
        guard let employeeId else { return }
        if let data = try? JSONEncoder().encode(pending) { defaults.set(data, forKey: Self.storageKey("pending-presence", employeeId: employeeId)) }
        if let data = try? JSONEncoder().encode(registrationHistory) { defaults.set(data, forKey: Self.storageKey("registration-history", employeeId: employeeId)) }
        if let data = try? JSONEncoder().encode(approvedAtByEventId) { defaults.set(data, forKey: Self.storageKey("approved-presence", employeeId: employeeId)) }
        pendingCount = pending.count
    }

    func isAwaitingUpload(_ id: UUID) -> Bool { pending.contains { $0.id == id } }
    func enqueue(_ event: CrewPresenceEvent) {
        guard employeeId != nil, seenEventIds.insert(event.id).inserted else { return }
        pending.append(event); registrationHistory.append(event); persist()
    }
    func uploadNewEvents(_ events: [CrewPresenceEvent]) async {
        events.forEach(enqueue); await retryPending()
    }
    func retryPending() async {
        guard !uploading, employeeId != nil, let token = deviceToken() else { return }
        let scope = generation
        uploading = true
        defer { uploading = false }
        while !Task.isCancelled, scope == generation, token == deviceToken(), let event = pending.first {
            do {
                try await send(event, token)
                // Await can resume after logout or another person's activation.
                guard scope == generation, token == deviceToken() else { return }
                pending.removeFirst(); persist(); lastUploadedEventId = event.id; lastError = nil
            } catch {
                guard scope == generation else { return }
                lastError = error.localizedDescription; return
            }
        }
    }
}
