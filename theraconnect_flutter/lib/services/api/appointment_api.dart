import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/appointment.dart';
import '../../models/clinician.dart';
import '../../models/schedule_slot.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class AppointmentApi {
  final ApiClient _client;

  AppointmentApi(this._client);

  Future<List<Clinician>> getClinicians() async {
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

  Future<List<ScheduleSlot>> getSchedules(String date, {int? clinicianId}) async {
    try {
      final response = await _client.get(
        ApiConfig.schedulesEndpoint,
        queryParameters: {
          'date': date,
          if (clinicianId != null) 'clinician_id': clinicianId,
        },
      );
      final data = response.data['data'] as List<dynamic>;
      return data
          .map((e) => ScheduleSlot.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  /// Y-m-d dates the clinician has at least one open slot in [from, to].
  Future<List<String>> getAvailability({
    required int clinicianId,
    required String from,
    required String to,
  }) async {
    try {
      final response = await _client.get(
        ApiConfig.availabilityEndpoint,
        queryParameters: {
          'clinician_id': clinicianId,
          'from': from,
          'to': to,
        },
      );
      final data = response.data['data'] as List<dynamic>;
      return data.map((e) => e as String).toList();
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<({List<Appointment> appointments, int currentPage, int lastPage, int total})>
      getAppointments({int page = 1}) async {
    try {
      final response = await _client.get(
        ApiConfig.appointmentsEndpoint,
        queryParameters: {'page': page},
      );
      final data = response.data['data'] as List<dynamic>;
      final meta = response.data['meta'] as Map<String, dynamic>;
      return (
        appointments: data
            .map((e) => Appointment.fromJson(e as Map<String, dynamic>))
            .toList(),
        currentPage: meta['current_page'] as int,
        lastPage: meta['last_page'] as int,
        total: meta['total'] as int,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Appointment> createAppointment({
    required String requestedAt,
    String mode = 'in_person',
    String? reason,
    int? clinicianId,
  }) async {
    try {
      final response = await _client.post(
        ApiConfig.appointmentsEndpoint,
        data: {
          'requested_at': requestedAt,
          'mode': mode,
          if (reason != null) 'reason': reason,
          if (clinicianId != null) 'clinician_id': clinicianId,
        },
      );
      return Appointment.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Appointment> getAppointment(int id) async {
    try {
      final response =
          await _client.get('${ApiConfig.appointmentsEndpoint}/$id');
      return Appointment.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Appointment> cancelAppointment(int id) async {
    try {
      final response =
          await _client.delete('${ApiConfig.appointmentsEndpoint}/$id');
      return Appointment.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
}
