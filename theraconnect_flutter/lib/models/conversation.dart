class Conversation {
  final String id;
  final String? clinicianId;
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
      id: json['public_id'] as String,
      clinicianId: json['clinician_public_id'] as String?,
      clinicianName: json['clinician_name'] as String?,
      lastMessage: json['last_message'] as String?,
      lastMessageAt: json['last_message_at'] as String?,
      unreadCount: (json['unread_count'] as int?) ?? 0,
    );
  }
}
