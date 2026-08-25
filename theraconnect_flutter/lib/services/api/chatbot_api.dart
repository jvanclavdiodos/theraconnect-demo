import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/chatbot_message.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class ChatbotApi {
  final ApiClient _client;

  ChatbotApi(this._client);

  Future<ChatbotReply> sendMessage(
    String message, {
    List<Map<String, String>> history = const [],
  }) async {
    try {
      final response = await _client.post(
        ApiConfig.chatbotMessageEndpoint,
        data: {
          'message': message,
          if (history.isNotEmpty) 'history': history,
        },
      );
      return ChatbotReply.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
