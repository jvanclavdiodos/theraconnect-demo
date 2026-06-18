import 'dart:io';
import 'package:firebase_messaging/firebase_messaging.dart';
import '../services/api/notification_api.dart';
import '../services/auth_service.dart';

/// Firebase Cloud Messaging integration for the TheraConnect patient app.
///
/// SCOPE: this service registers the device token with the backend so push
/// notifications can be delivered when the app is BACKGROUNDED or KILLED.
/// Notifications are delivered by the OS notification tray in that case.
///
/// Foreground display + deep-link navigation are NOT implemented here:
///   - When the app is in the FOREGROUND, incoming FCM messages are
///     silently dropped (no banner/sound/notification shows).
///   - Tapping a notification does not deep-link to a specific screen.
/// Both gaps require `flutter_local_notifications` (to render a banner while
/// the app is in the foreground) and a navigation hook into the router
/// (to handle the tap). See: https://firebase.flutter.dev/docs/messaging/usage#foreground-notifications
/// Both are deferred until FCM credentials are provisioned for the pilot
/// (see .env.railway.example FCM section) and the package can be validated
/// locally against an emulator.
///
/// In-app notifications still work end-to-end because NotificationService
/// (backend, app/Services/NotificationService.php) writes the DB row
/// synchronously — the Flutter app polls /api/v1/notifications on each
/// app foreground / pull-to-refresh.
class FcmService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final NotificationApi _notificationApi;
  final AuthService _authService;

  FcmService({required NotificationApi notificationApi, required AuthService authService})
      : _notificationApi = notificationApi,
        _authService = authService;

  Future<void> initialize() async {
    await _requestPermission();
    _registerOnTokenRefresh();
    FirebaseMessaging.onBackgroundMessage(_backgroundHandler);

    final token = await _messaging.getToken();
    if (token != null) {
      await _registerToken(token);
    }
  }

  /// No-op background isolate handler. Required so FirebaseMessaging's
  /// Dart isolate can boot when a push arrives while the app is killed.
  /// Actual notification display in this state is handled by the OS
  /// notification tray (no plugin required).
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

  void _registerOnTokenRefresh() {
    _messaging.onTokenRefresh.listen((newToken) {
      _registerToken(newToken);
    });
  }

  Future<void> _registerToken(String token) async {
    final hasToken = await _authService.hasToken();
    if (!hasToken) return;

    final platform = Platform.isIOS ? 'ios' : 'android';
    // Token registration failures are non-fatal (push is optional), but they
    // must NOT be silently swallowed otherwise the patient is silently
    // unsubscribed from push. Log so the issue is discoverable during dev
    // and via the OS log; future work should surface this to Sentry/Logcat.
    try {
      await _notificationApi.registerDeviceToken(token: token, platform: platform);
    } catch (e) {
      // ignore: avoid_print
      print('FCM: token registration failed: $e');
    }
  }

  Future<void> unregisterToken() async {
    final token = await _messaging.getToken();
    if (token != null) {
      try {
        await _notificationApi.removeDeviceToken(token);
      } catch (e) {
        // ignore: avoid_print
        print('FCM: token removal failed: $e');
      }
    }
  }

  Future<String?> getToken() async {
    return await _messaging.getToken();
  }
}
