class Message {
  final String id;
  final String conversationId;
  final String senderId;
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
      id: json['public_id'] as String,
      conversationId: json['conversation_public_id'] as String,
      senderId: json['sender_public_id'] as String,
      senderName: json['sender_name'] as String?,
      isMine: (json['is_mine'] as bool?) ?? false,
      body: json['body'] as String,
      createdAt: json['created_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'public_id': id,
        'conversation_public_id': conversationId,
        'sender_public_id': senderId,
        'sender_name': senderName,
        'is_mine': isMine,
        'body': body,
        'created_at': createdAt,
      };
}
