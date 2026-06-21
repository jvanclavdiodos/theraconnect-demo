class Message {
  final int id;
  final int conversationId;
  final int senderId;
  final String? senderName;
  final bool isMine;
  final String body;
  final String? createdAt;

  const Message({
    required this.id,
    required this.conversationId,
    required this.senderId,
    this.senderName,
    required this.isMine,
    required this.body,
    this.createdAt,
  });

  factory Message.fromJson(Map<String, dynamic> json) {
    return Message(
      id: json['id'] as int,
      conversationId: json['conversation_id'] as int,
      senderId: json['sender_id'] as int,
      senderName: json['sender_name'] as String?,
      isMine: (json['is_mine'] as bool?) ?? false,
      body: json['body'] as String,
      createdAt: json['created_at'] as String?,
    );
  }
}
