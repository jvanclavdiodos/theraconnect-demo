import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/patient.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class ProfileApi {
  final ApiClient _client;

  ProfileApi(this._client);

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
    String? contactNo,
    String? address,
    String? emergencyContact,
  }) async {
    try {
      final response = await _client.put(ApiConfig.profileEndpoint, data: {
        if (dateOfBirth != null) 'date_of_birth': dateOfBirth,
        if (contactNo != null) 'contact_no': contactNo,
        if (address != null) 'address': address,
        if (emergencyContact != null) 'emergency_contact': emergencyContact,
      });
      return Patient.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
