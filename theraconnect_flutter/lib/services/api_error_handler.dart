import 'package:dio/dio.dart';
import '../models/api_response.dart';

ApiError handleDioError(DioException e) {
  if (e.response?.data is Map) {
    return ApiError.fromJson(
      e.response!.data as Map<String, dynamic>,
      statusCode: e.response?.statusCode ?? 0,
    );
  }
  return ApiError(
    message: 'You appear to be offline. Check your connection and try again.',
    statusCode: e.response?.statusCode ?? 0,
  );
}
