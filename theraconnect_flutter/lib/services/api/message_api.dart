import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/conversation.dart';
import '../../models/message.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class MessageApi {
  final ApiClient _client;

  MessageApi(this._client);

  Future<List<Conversation>> getConversations() async {
    try {
      final response = await _client.get(ApiConfig.conversationsEndpoint);
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => Conversation.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Open (or create) the thread with the patient's assigned clinician.
  Future<Conversation> ensureConversation() async {
    try {
      final response = await _client.post(ApiConfig.conversationsEndpoint);
      return Conversation.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<List<Message>> getMessages(int conversationId) async {
    try {
      final response = await _client
          .get('${ApiConfig.conversationsEndpoint}/$conversationId/messages');
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => Message.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Message> sendMessage(int conversationId, String body) async {
    try {
      final response = await _client.post(
        '${ApiConfig.conversationsEndpoint}/$conversationId/messages',
        data: {'body': body},
      );
      return Message.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
