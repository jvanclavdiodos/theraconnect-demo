import 'dart:io';
import 'package:firebase_messaging/firebase_messaging.dart';
import '../services/api/notification_api.dart';
import '../services/auth_service.dart';

class FcmService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final NotificationApi _notificationApi;
  final AuthService _authService;

  FcmService({required NotificationApi notificationApi, required AuthService authService})
      : _notificationApi = notificationApi,
        _authService = authService;

  Future<void> initialize() async {
    await _requestPermission();
    _setupForegroundMessageHandler();
    _setupNotificationOpenedHandler();

    FirebaseMessaging.onBackgroundMessage(_backgroundHandler);

    final token = await _messaging.getToken();
    if (token != null) {
      await _registerToken(token);
    }

    _messaging.onTokenRefresh.listen((newToken) {
      _registerToken(newToken);
    });
  }

  @pragma('vm:entry-point')
  static Future<void> _backgroundHandler(RemoteMessage message) async {
    return;
  }

  Future<void> _requestPermission() async {
    await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  void _setupForegroundMessageHandler() {
    FirebaseMessaging.onMessage.listen((message) {
      final notification = message.notification;
      if (notification != null) {}
    });
  }

  void _setupNotificationOpenedHandler() {
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      final data = message.data;
    });
  }

  Future<void> _registerToken(String token) async {
    final hasToken = await _authService.hasToken();
    if (!hasToken) return;

    final platform = Platform.isIOS ? 'ios' : 'android';
    try {
      await _notificationApi.registerDeviceToken(token: token, platform: platform);
    } catch (_) {}
  }

  Future<void> unregisterToken() async {
    final token = await _messaging.getToken();
    if (token != null) {
      try {
        await _notificationApi.removeDeviceToken(token);
      } catch (_) {}
    }
  }

  Future<String?> getToken() async {
    return await _messaging.getToken();
  }
}
