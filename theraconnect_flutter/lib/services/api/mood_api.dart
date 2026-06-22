import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/mood_log.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class MoodApi {
  final ApiClient _client;

  MoodApi(this._client);

  /// Recent mood check-ins, newest first.
  Future<List<MoodLog>> getMoodLogs() async {
    try {
      final response = await _client.get(ApiConfig.moodLogsEndpoint);
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => MoodLog.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Log a mood check-in (1–10, optional note).
  Future<MoodLog> logMood(int score, {String? note}) async {
    try {
      final response = await _client.post(ApiConfig.moodLogsEndpoint, data: {
        'score': score,
        if (note != null && note.isNotEmpty) 'note': note,
      });
      return MoodLog.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
