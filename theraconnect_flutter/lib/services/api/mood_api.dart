import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/mood_log.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class MoodApi {
  final ApiClient _client;

  MoodApi(this._client);

  /// Recent mood check-ins, newest first.
  Future<MoodLogFeed> getMoodLogs() async {
    try {
      final response = await _client.get(ApiConfig.moodLogsEndpoint);
      final data = response.data['data'] as List<dynamic>;
      final logs =
          data.map((e) => MoodLog.fromJson(e as Map<String, dynamic>)).toList();
      final meta = response.data['meta'] as Map<String, dynamic>;
      final todayLogJson = meta['today_log'];

      return MoodLogFeed(
        logs: logs,
        today: meta['today'] as String,
        todayCompleted: meta['today_completed'] as bool? ?? false,
        todayLog: todayLogJson is Map<String, dynamic>
            ? MoodLog.fromJson(todayLogJson)
            : null,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Log today's mood check-in (1-10, optional note).
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
