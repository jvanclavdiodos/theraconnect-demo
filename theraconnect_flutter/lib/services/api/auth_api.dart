import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/clinician.dart';
import '../../models/user.dart';
import '../../models/patient.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class AuthApi {
  final ApiClient _client;

  AuthApi(this._client);

  /// Public clinician directory used to populate the sign-up picker (works
  /// pre-auth — the endpoint requires no bearer token).
  Future<List<Clinician>> fetchClinicians() async {
    try {
      final response = await _client.get(ApiConfig.cliniciansEndpoint);
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => Clinician.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<({User user, String token})> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? contactNo,
    String? gender,
    String? educationalAttainment,
    String? employmentStatus,
    String? personalIssues,
    int? requestedClinicianId,
  }) async {
    try {
      final response = await _client.post(ApiConfig.registerEndpoint, data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        if (contactNo != null) 'contact_no': contactNo,
        if (gender != null) 'gender': gender,
        if (educationalAttainment != null) 'educational_attainment': educationalAttainment,
        if (employmentStatus != null) 'employment_status': employmentStatus,
        if (personalIssues != null) 'personal_issues': personalIssues,
        if (requestedClinicianId != null) 'requested_clinician_id': requestedClinicianId,
      });
      final data = response.data['data'];
      return (
        user: User.fromJson(data['user']),
        token: data['token'] as String,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<({User user, String token})> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await _client.post(ApiConfig.loginEndpoint, data: {
        'email': email,
        'password': password,
      });
      final data = response.data['data'];
      return (
        user: User.fromJson(data['user']),
        token: data['token'] as String,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String newPasswordConfirmation,
  }) async {
    try {
      await _client.put('/auth/password', data: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPasswordConfirmation,
      });
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<void> logout() async {
    try {
      await _client.post(ApiConfig.logoutEndpoint);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<({User user, Patient? patientProfile})> me() async {
    try {
      final response = await _client.get(ApiConfig.meEndpoint);
      final data = response.data['data'];
      return (
        user: User.fromJson(data['user']),
        patientProfile: data['patient_profile'] != null
            ? Patient.fromJson(data['patient_profile'])
            : null,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
