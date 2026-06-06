import 'package:dio/dio.dart';
import '../../config/api_config.dart';
import '../../models/notification_item.dart';
import '../api_client.dart';
import '../api_error_handler.dart';

class NotificationApi {
  final ApiClient _client;

  NotificationApi(this._client);

  Future<({List<NotificationItem> notifications, int currentPage, int lastPage, int total})>
      getNotifications({int page = 1}) async {
    try {
      final response = await _client.get(
        ApiConfig.notificationsEndpoint,
        queryParameters: {'page': page},
      );
      final data = response.data['data'] as List<dynamic>;
      final meta = response.data['meta'] as Map<String, dynamic>;
      return (
        notifications: data
            .map((e) =>
                NotificationItem.fromJson(e as Map<String, dynamic>))
            .toList(),
        currentPage: meta['current_page'] as int,
        lastPage: meta['last_page'] as int,
        total: meta['total'] as int,
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<NotificationItem> markRead(int id) async {
    try {
      final response =
          await _client.post('${ApiConfig.notificationsEndpoint}/$id/read');
      return NotificationItem.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<Map<String, dynamic>> registerDeviceToken({
    required String token,
    required String platform,
  }) async {
    try {
      final response = await _client.post(
        ApiConfig.deviceTokenEndpoint,
        data: {'token': token, 'platform': platform},
      );
      return response.data['data'] as Map<String, dynamic>;
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }

  Future<void> removeDeviceToken(String token) async {
    try {
      await _client.delete(
        ApiConfig.deviceTokenEndpoint,
        data: {'token': token},
      );
    } on DioException catch (e) {
      throw handleDioError(e);
    }
  }
