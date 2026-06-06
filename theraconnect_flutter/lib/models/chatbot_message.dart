class ChatbotReply {
  final String reply;
  final String intentKey;
  final bool isFallback;

  const ChatbotReply({
    required this.reply,
    required this.intentKey,
    required this.isFallback,
  });

  factory ChatbotReply.fromJson(Map<String, dynamic> json) {
    return ChatbotReply(
      reply: json['reply'] as String,
      intentKey: json['intent_key'] as String,
      isFallback: json['is_fallback'] as bool,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'reply': reply,
      'intent_key': intentKey,
      'is_fallback': isFallback,
    };
  }
}

class ChatMessage {
  final String text;
  final bool isUser;

  const ChatMessage({required this.text, required this.isUser});
}
