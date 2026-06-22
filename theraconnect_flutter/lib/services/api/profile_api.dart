import 'dart:typed_data';
import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/patient.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class ProfileApi {
  final ApiClient _client;

  ProfileApi(this._client);

  Future<Patient> uploadAvatar(String filePath) async {
    try {
      final response = await _client.postMultipart(
        '${ApiConfig.profileEndpoint}/avatar',
        data: const {},
        filePath: filePath,
        fileField: 'avatar',
      );
      return Patient.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Uint8List> getAvatarBytes() async {
    try {
      final response = await _client.dio.get(
        '${ApiConfig.profileEndpoint}/avatar',
        options: Options(responseType: ResponseType.bytes),
      );
      return Uint8List.fromList(List<int>.from(response.data as List));
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Patient> getProfile() async {
    try {
      final response = await _client.get(ApiConfig.profileEndpoint);
      return Patient.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Patient> updateProfile({
    String? dateOfBirth,
    String? gender,
    String? educationalAttainment,
    String? employmentStatus,
    String? personalIssues,
    String? contactNo,
    String? address,
    String? emergencyContact,
  }) async {
    try {
      final response = await _client.put(ApiConfig.profileEndpoint, data: {
        'date_of_birth': dateOfBirth,
        'gender': gender,
        'educational_attainment': educationalAttainment,
        'employment_status': employmentStatus,
        'personal_issues': personalIssues,
        'contact_no': contactNo,
        'address': address,
        'emergency_contact': emergencyContact,
      });
      return Patient.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
