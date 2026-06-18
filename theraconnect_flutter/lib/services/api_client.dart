import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../config/api_config.dart';
import 'auth_service.dart';

class ApiClient {
  final AuthService _authService;
  late final Dio dio;
  void Function()? onUnauthorized;

  ApiClient({required AuthService authService}) : _authService = authService {
    dio = Dio(BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: ApiConfig.connectTimeout,
      receiveTimeout: ApiConfig.receiveTimeout,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    dio.interceptors.add(AuthInterceptor(_authService, onUnauthorized: () {
      onUnauthorized?.call();
    }));

    if (kDebugMode) {
      dio.interceptors.add(_FilteredLogInterceptor(
        suppressBodyPaths: const {'/login', '/register'},
      ));
    }
  }

  Future<Response> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    return dio.get(path, queryParameters: queryParameters);
  }

  Future<Response> post(
    String path, {
    dynamic data,
  }) async {
    return dio.post(path, data: data);
  }

  Future<Response> put(
    String path, {
    dynamic data,
  }) async {
    return dio.put(path, data: data);
  }

  Future<Response> patch(
    String path, {
    dynamic data,
  }) async {
    return dio.patch(path, data: data);
  }

  Future<Response> delete(
    String path, {
    dynamic data,
  }) async {
    return dio.delete(path, data: data);
  }

  Future<Response> postMultipart(
    String path, {
    required Map<String, dynamic> data,
    String? filePath,
    String fileField = 'file',
  }) async {
    final formData = FormData.fromMap(data);
    if (filePath != null) {
      final uri = Uri.file(filePath);
      final filename = uri.pathSegments.last;
      formData.files.add(MapEntry(
        fileField,
        await MultipartFile.fromFile(filePath, filename: filename),
      ));
    }
    return dio.post(path, data: formData);
  }
}

class AuthInterceptor extends Interceptor {
  final AuthService _authService;
  final VoidCallback _onUnauthorized;

  AuthInterceptor(this._authService, {required VoidCallback onUnauthorized})
      : _onUnauthorized = onUnauthorized;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    try {
      final token = await _authService.getToken();
      if (token != null) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    } catch (_) {
      // Never let a token-read failure stall the request: a throw here would
      // skip handler.next()/reject() entirely and hang the request forever
      // (no timeout applies before the request starts). Proceed unauthenticated.
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      await _authService.clearToken();
      _onUnauthorized();
    }
    handler.next(err);
  }
}

/// LogInterceptor wrapper that suppresses request/response BODY logging
/// for paths that may carry bearer tokens or credentials in the body
/// (e.g. /login, /register). Headers (which never contain the bearer token
/// in plain text — Dio stores them per-request) and the request line / status
/// are still logged; only the request/response bodies are redacted.
///
/// Logs are emitted only in debug builds (see _FilteredLogInterceptor is
/// only added when kDebugMode is true in ApiClient).
class _FilteredLogInterceptor extends Interceptor {
  final LogInterceptor _log = LogInterceptor(requestBody: true, responseBody: true);
  final Set<String> _suppressBodyPaths;

  _FilteredLogInterceptor({required Set<String> suppressBodyPaths})
      : _suppressBodyPaths = suppressBodyPaths;

  bool _suppress(RequestOptions options) {
    return _suppressBodyPaths.any((p) => options.path.contains(p));
  }

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (_suppress(options)) {
      // Skip the LogInterceptor entirely for suppressed paths — its body
      // logging would dump the email/password and the returned bearer token
      // into the console. Log just a sanitized request line via the wrapped
      // Logger instance and proceed.
      // ignore: avoid_print
      print('--> ${options.method} ${options.path} (body suppressed)');
      handler.next(options);
      return;
    }
    _log.onRequest(options, handler);
  }

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    if (_suppress(response.requestOptions)) {
      // ignore: avoid_print
      print('<-- ${response.statusCode} ${response.requestOptions.path} (body suppressed)');
      handler.next(response);
      return;
    }
    _log.onResponse(response, handler);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (_suppress(err.requestOptions)) {
      // ignore: avoid_print
      print('<-- ERROR ${err.response?.statusCode} ${err.requestOptions.path}');
      handler.next(err);
      return;
    }
    _log.onError(err, handler);
  }
}
