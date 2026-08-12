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

  Map<String, dynamic> toJson() => {
        'public_id': id,
        'clinician_public_id': clinicianId,
        'clinician_name': clinicianName,
        'last_message': lastMessage,
        'last_message_at': lastMessageAt,
        'unread_count': unreadCount,
      };
}
