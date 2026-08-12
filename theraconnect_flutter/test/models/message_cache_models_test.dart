import 'package:flutter_test/flutter_test.dart';
import 'package:theraconnect/models/api_response.dart';
import 'package:theraconnect/models/conversation.dart';
import 'package:theraconnect/models/message.dart';

void main() {
  test('conversation survives encrypted-cache serialization', () {
    const conversation = Conversation(
      id: '01CONVERSATION',
      clinicianId: '01CLINICIAN',
      clinicianName: 'Dr. Santos',
      lastMessage: 'See you tomorrow.',
      lastMessageAt: '2026-08-12T09:00:00+08:00',
      unreadCount: 2,
    );

    final restored = Conversation.fromJson(conversation.toJson());

    expect(restored.id, conversation.id);
    expect(restored.clinicianName, conversation.clinicianName);
    expect(restored.lastMessage, conversation.lastMessage);
    expect(restored.unreadCount, conversation.unreadCount);
  });

  test('message survives encrypted-cache serialization', () {
    const message = Message(
      id: '01MESSAGE',
      conversationId: '01CONVERSATION',
      senderId: '01SENDER',
      senderName: 'Dr. Santos',
      isMine: false,
      body: 'How have you been feeling?',
      createdAt: '2026-08-12T09:00:00+08:00',
    );

    final restored = Message.fromJson(message.toJson());

    expect(restored.id, message.id);
    expect(restored.conversationId, message.conversationId);
    expect(restored.isMine, isFalse);
    expect(restored.body, message.body);
  });

  test('status zero errors are treated as network failures', () {
    const error = ApiError(message: 'Offline', statusCode: 0);
    const serverError = ApiError(message: 'Failed', statusCode: 500);

    expect(error.isNetworkError, isTrue);
    expect(serverError.isNetworkError, isFalse);
  });
}
