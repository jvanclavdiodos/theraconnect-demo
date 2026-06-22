import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/assessment.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class AssessmentApi {
  final ApiClient _client;

  AssessmentApi(this._client);

  /// The patient's questionnaires — pending first, then completed history.
  Future<List<Assessment>> getAssessments() async {
    try {
      final response = await _client.get(ApiConfig.assessmentsEndpoint);
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => Assessment.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Full questionnaire (items + options) to render the fillable form.
  Future<AssessmentDetail> getAssessment(int id) async {
    try {
      final response = await _client.get('${ApiConfig.assessmentsEndpoint}/$id');
      return AssessmentDetail.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Submit responses (one 0–3 per item); returns the scored assessment.
  Future<Assessment> submit(int id, List<int> responses) async {
    try {
      final response = await _client.post(
        '${ApiConfig.assessmentsEndpoint}/$id/submit',
        data: {'responses': responses},
      );
      return Assessment.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
