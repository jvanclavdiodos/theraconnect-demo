import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/conversation.dart';
import '../services/api/message_api.dart';
import 'auth_provider.dart';

final messageApiProvider = Provider<MessageApi>((ref) {
  return MessageApi(ref.watch(apiClientProvider));
});

/// One conversation for each clinician assigned after appointment approval.
final conversationsProvider =
    FutureProvider.autoDispose<List<Conversation>>((ref) async {
  return ref.watch(messageApiProvider).getConversations();
});
