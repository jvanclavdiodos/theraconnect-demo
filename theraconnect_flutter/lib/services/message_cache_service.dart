import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/conversation.dart';
import '../models/message.dart';

class CachedMessages {
  final List<Message> messages;
  final DateTime savedAt;

  const CachedMessages(this.messages, this.savedAt);
}

/// Encrypted, account-scoped snapshots used only for read-only offline access.
class MessageCacheService {
  static const _indexKey = 'message_cache_keys';
  static const _conversationListPrefix = 'message_conversations_';
  static const _threadPrefix = 'message_thread_';
  static const _maxMessagesPerThread = 50;

  final FlutterSecureStorage _storage;

  MessageCacheService({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  Future<void> saveConversations(
    String userId,
    List<Conversation> conversations,
  ) async {
    try {
      final key = '$_conversationListPrefix$userId';
      await _writeIndexed(
        key,
        jsonEncode({
          'saved_at': DateTime.now().toUtc().toIso8601String(),
          'items': conversations.map((item) => item.toJson()).toList(),
        }),
      );
    } catch (_) {}
  }

  Future<List<Conversation>?> readConversations(String userId) async {
    try {
      final raw = await _storage.read(key: '$_conversationListPrefix$userId');
      if (raw == null) return null;
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      return (decoded['items'] as List<dynamic>)
          .map((item) => Conversation.fromJson(item as Map<String, dynamic>))
          .toList();
    } catch (_) {
      return null;
    }
  }

  Future<void> saveMessages(
    String userId,
    String conversationId,
    List<Message> messages,
  ) async {
    try {
      final retained = messages.length > _maxMessagesPerThread
          ? messages.sublist(messages.length - _maxMessagesPerThread)
          : messages;
      final key = '$_threadPrefix${userId}_$conversationId';
      await _writeIndexed(
        key,
        jsonEncode({
          'saved_at': DateTime.now().toUtc().toIso8601String(),
          'items': retained.map((item) => item.toJson()).toList(),
        }),
      );
    } catch (_) {}
  }

  Future<CachedMessages?> readMessages(
    String userId,
    String conversationId,
  ) async {
    try {
      final raw = await _storage.read(
        key: '$_threadPrefix${userId}_$conversationId',
      );
      if (raw == null) return null;
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      final messages = (decoded['items'] as List<dynamic>)
          .map((item) => Message.fromJson(item as Map<String, dynamic>))
          .toList();
      return CachedMessages(
        messages,
        DateTime.parse(decoded['saved_at'] as String).toLocal(),
      );
    } catch (_) {
      return null;
    }
  }

  Future<void> clear() async {
    try {
      final keys = await _readIndex();
      for (final key in keys) {
        await _storage.delete(key: key);
      }
      await _storage.delete(key: _indexKey);
    } catch (_) {
      // Cache cleanup must not block logout if the platform keystore fails.
    }
  }

  Future<void> _writeIndexed(String key, String value) async {
    await _storage.write(key: key, value: value);
    final keys = await _readIndex();
    if (keys.add(key)) {
      await _storage.write(key: _indexKey, value: jsonEncode(keys.toList()));
    }
  }

  Future<Set<String>> _readIndex() async {
    final raw = await _storage.read(key: _indexKey);
    if (raw == null) return <String>{};
    try {
      return (jsonDecode(raw) as List<dynamic>)
          .map((item) => item.toString())
          .toSet();
    } catch (_) {
      return <String>{};
    }
  }
}
