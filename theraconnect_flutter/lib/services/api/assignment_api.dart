import 'dart:typed_data';
import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/assignment.dart';
import '../../models/downloaded_file.dart';
import '../../models/submission.dart';
import '../api_client.dart';
import '../api_error_handler.dart';
import '../download_service.dart';

class AssignmentApi {
  final ApiClient _client;
  final DownloadService _downloads;

  AssignmentApi(this._client, this._downloads);

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

  /// Downloads the clinician's worksheet for [assignmentId] through the
  /// authenticated Dio client (the bearer token is attached by the interceptor)
  /// and persists it via [DownloadService]: a durable app-owned copy plus a
  /// public Download/TheraConnect copy, recorded in the in-app Downloads index.
  /// Returns the resulting [DownloadedFile].
  Future<DownloadedFile> downloadWorksheet(
    int assignmentId,
    String fileName,
    String title,
  ) async {
    try {
      return await _downloads.downloadAndStore(
        urlPath: '${ApiConfig.assignmentsEndpoint}/$assignmentId/worksheet',
        fileName: fileName,
        title: title,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Raw bytes of a submission file (for inline image/text preview), fetched
  /// through the authenticated Dio client.
  Future<Uint8List> getSubmissionBytes(int submissionId) async {
    try {
      final response = await _client.dio.get(
        '/submissions/$submissionId/file',
        options: Options(responseType: ResponseType.bytes),
      );
      return Uint8List.fromList(List<int>.from(response.data as List));
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Download a submission file to device storage and record it in Downloads
  /// (used for file types we don't preview inline, e.g. PDF/doc).
  Future<DownloadedFile> downloadSubmission(
    int submissionId,
    String fileName,
    String title,
  ) async {
    try {
      return await _downloads.downloadAndStore(
        urlPath: '/submissions/$submissionId/file',
        fileName: fileName,
        title: title,
      );
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
}
