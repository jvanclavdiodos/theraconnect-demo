import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/assignment.dart';
import '../../models/submission.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class AssignmentApi {
  final ApiClient _client;

  AssignmentApi(this._client);

  Future<({List<Assignment> assignments, int currentPage, int lastPage, int total})>
      getAssignments({int page = 1}) async {
    try {
      final response = await _client.get(
        ApiConfig.assignmentsEndpoint,
        queryParameters: {'page': page},
      );
      final data = response.data['data'] as List<dynamic>;
      final meta = response.data['meta'] as Map<String, dynamic>;
      return (
        assignments: data
            .map((e) => Assignment.fromJson(e as Map<String, dynamic>))
            .toList(),
        currentPage: meta['current_page'] as int,
        lastPage: meta['last_page'] as int,
        total: meta['total'] as int,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Assignment> getAssignment(int id) async {
    try {
      final response =
          await _client.get('${ApiConfig.assignmentsEndpoint}/$id');
      return Assignment.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Submission> submitAssignment(
    int assignmentId, {
    String? content,
    String? filePath,
  }) async {
    try {
      final response = await _client.postMultipart(
        '${ApiConfig.assignmentsEndpoint}/$assignmentId/submit',
        data: {
          if (content != null) 'content': content,
        },
        filePath: filePath,
      );
      return Submission.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
