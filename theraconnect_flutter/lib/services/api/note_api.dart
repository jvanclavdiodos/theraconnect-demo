import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/patient_note.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class NoteApi {
  final ApiClient _client;

  NoteApi(this._client);

  /// Notes the clinician has shared with this patient (read-only).
  Future<List<PatientNote>> getNotes() async {
    try {
      final response = await _client.get(ApiConfig.notesEndpoint);
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => PatientNote.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
