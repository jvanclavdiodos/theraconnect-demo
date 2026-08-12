import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/api_response.dart';
import '../models/conversation.dart';
import '../services/api/message_api.dart';
import 'auth_provider.dart';

class ConversationSnapshot {
  final List<Conversation> conversations;
  final bool isOffline;

  const ConversationSnapshot(this.conversations, {this.isOffline = false});
}

final messageApiProvider = Provider<MessageApi>((ref) {
  return MessageApi(ref.watch(apiClientProvider));
});

/// One conversation for each clinician assigned after appointment approval.
final conversationsProvider =
    FutureProvider.autoDispose<ConversationSnapshot>((ref) async {
  final userId = ref.watch(authProvider).user?.id;
  if (userId == null) return const ConversationSnapshot([]);

  final cache = ref.watch(messageCacheServiceProvider);
  try {
    final conversations =
        await ref.watch(messageApiProvider).getConversations();
    await cache.saveConversations(userId, conversations);
    return ConversationSnapshot(conversations);
  } catch (error) {
    final apiError = ApiError.fromException(error);
    if (!apiError.isNetworkError) rethrow;
    final cached = await cache.readConversations(userId);
    if (cached == null) rethrow;
    return ConversationSnapshot(cached, isOffline: true);
  }
});
