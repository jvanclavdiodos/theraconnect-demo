class Conversation {
  final int id;
  final int? clinicianId;
  final String? clinicianName;
  final String? lastMessage;
  final String? lastMessageAt;
  final int unreadCount;

  const Conversation({
    required this.id,
    this.clinicianId,
    this.clinicianName,
    this.lastMessage,
    this.lastMessageAt,
    this.unreadCount = 0,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) {
    return Conversation(
      id: json['id'] as int,
      clinicianId: json['clinician_id'] as int?,
      clinicianName: json['clinician_name'] as String?,
      lastMessage: json['last_message'] as String?,
      lastMessageAt: json['last_message_at'] as String?,
      unreadCount: (json['unread_count'] as int?) ?? 0,
    );
  }
}
