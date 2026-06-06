import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/chatbot_message.dart';
import '../models/api_response.dart';
import '../services/api/chatbot_api.dart';
import 'auth_provider.dart';

final chatbotApiProvider = Provider<ChatbotApi>((ref) {
  final client = ref.watch(apiClientProvider);
  return ChatbotApi(client);
});

class ChatbotNotifier extends StateNotifier<AsyncValue<List<ChatMessage>>> {
  final ChatbotApi _api;

  ChatbotNotifier(this._api) : super(const AsyncValue.data([]));

  Future<void> sendMessage(String text) async {
    final currentMessages = List<ChatMessage>.from(state.valueOrNull ?? []);
    final updated = [...currentMessages, ChatMessage(text: text, isUser: true)];

    state = AsyncValue.data([...updated, ChatMessage(text: '...', isUser: false)]);

    try {
      final reply = await _api.sendMessage(text);
      final finalMessages = [
        ...updated,
        ChatMessage(text: reply.reply, isUser: false),
      ];
      state = AsyncValue.data(finalMessages);
    } catch (e) {
      final errorMessages = [
        ...updated,
        ChatMessage(
          text: e is ApiError ? e.userMessage : 'Sorry, something went wrong. Please try again.',
          isUser: false,
        ),
      ];
      state = AsyncValue.data(errorMessages);
    }
  }

  void clearMessages() {
    state = const AsyncValue.data([]);
  }
}

final chatbotProvider =
    StateNotifierProvider<ChatbotNotifier, AsyncValue<List<ChatMessage>>>((ref) {
  return ChatbotNotifier(ref.watch(chatbotApiProvider));
});
