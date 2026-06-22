import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/therapy_goal.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class GoalsApi {
  final ApiClient _client;

  GoalsApi(this._client);

  /// The patient's therapy goals (active + met), with each goal's latest rating.
  Future<List<TherapyGoal>> getGoals() async {
    try {
      final response = await _client.get(ApiConfig.goalsEndpoint);
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => TherapyGoal.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
