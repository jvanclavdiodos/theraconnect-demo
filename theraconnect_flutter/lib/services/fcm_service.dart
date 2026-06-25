import 'dart:io';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../services/api/notification_api.dart';
import '../services/auth_service.dart';

/// Firebase Cloud Messaging integration for TheraConnect.
///
/// Delivery in every app state:
///   - BACKGROUND / KILLED  → the OS posts the FCM `notification` to the tray
///     (channel `theraconnect_default`, declared in AndroidManifest).
///   - FOREGROUND           → `onMessage` renders a banner via
///     flutter_local_notifications (the OS suppresses tray banners while the
///     app is foregrounded) and refreshes the in-app notifications list.
///   - TAP (any state)      → `onMessageOpenedApp` / `getInitialMessage`
///     deep-link to the relevant screen using the push `data` payload
///     (`type`, plus `appointment_id` / `assignment_id` when present).
///
/// Requires `android/app/google-services.json` + the gms Gradle plugin and,
/// on the backend, `FCM_PROJECT_ID` + `FCM_CREDENTIALS`. With none of these the
/// app still works: token registration simply fails and in-app notifications
/// continue to work via polling /api/v1/notifications.
class FcmService {
  static const _channelId = 'theraconnect_default';
  static const _channelName = 'General';

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();
  final NotificationApi _notificationApi;
  final AuthService _authService;

  /// Navigate to an in-app route when a push is tapped.
  void Function(String route)? onNavigate;

  /// Refresh the in-app notifications list when a push arrives in foreground.
  Future<void> Function()? onForegroundRefresh;

  FcmService({required NotificationApi notificationApi, required AuthService authService})
      : _notificationApi = notificationApi,
        _authService = authService;

  Future<void> initialize({
    void Function(String route)? onNavigate,
    Future<void> Function()? onForegroundRefresh,
  }) async {
    this.onNavigate = onNavigate;
    this.onForegroundRefresh = onForegroundRefresh;

    await _requestPermission();
    await _initLocalNotifications();

    FirebaseMessaging.onBackgroundMessage(_backgroundHandler);

    // Foreground messages: render a banner + refresh the list.
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Tap on a tray notification that opened/resumed the app.
    FirebaseMessaging.onMessageOpenedApp.listen(_handleTap);

    // App launched from terminated by tapping a notification.
    final initial = await _messaging.getInitialMessage();
    if (initial != null) {
      _handleTap(initial);
    }

    _registerOnTokenRefresh();
    final token = await _messaging.getToken();
    if (token != null) {
      await _registerToken(token);
    }
  }

  Future<void> _initLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosInit = DarwinInitializationSettings();
    await _local.initialize(
      const InitializationSettings(android: androidInit, iOS: iosInit),
      onDidReceiveNotificationResponse: (response) {
        final route = response.payload;
        if (route != null && route.isNotEmpty) {
          onNavigate?.call(route);
        }
      },
    );

    // Channel must match the manifest's default_notification_channel_id.
    const channel = AndroidNotificationChannel(
      _channelId,
      _channelName,
      description: 'Appointment, assignment, and message alerts',
      importance: Importance.high,
    );
    await _local
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
  }

  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    final notification = message.notification;
    if (notification != null) {
      await _local.show(
        notification.hashCode,
        notification.title,
        notification.body,
        NotificationDetails(
          android: const AndroidNotificationDetails(
            _channelId,
            _channelName,
            importance: Importance.high,
            priority: Priority.high,
          ),
          iOS: const DarwinNotificationDetails(),
        ),
        payload: _routeFor(message.data),
      );
    }
    await onForegroundRefresh?.call();
  }

  void _handleTap(RemoteMessage message) {
    onNavigate?.call(_routeFor(message.data));
  }

  /// Maps the backend push `data` payload to an in-app route.
  String _routeFor(Map<String, dynamic> data) {
    final type = (data['type'] ?? '').toString();
    final appointmentId = data['appointment_id']?.toString();
    final assignmentId = data['assignment_id']?.toString();

    if (type.startsWith('appointment')) {
      return appointmentId != null ? '/appointments/$appointmentId' : '/appointments';
    }
    if (type == 'message_received') {
      return '/messages';
    }
    if (type == 'assessment_assigned') {
      return '/assessments';
    }
    if (type.startsWith('assignment')) {
      return assignmentId != null ? '/assignments/$assignmentId' : '/assignments';
    }
    return '/notifications';
  }

  /// No-op background isolate handler. Required so FirebaseMessaging's Dart
  /// isolate can boot when a push arrives while the app is killed; the OS tray
  /// renders the notification in that state (no plugin work needed here).
  @pragma('vm:entry-point')
  static Future<void> _backgroundHandler(RemoteMessage message) async {
    return;
  }

  Future<void> _requestPermission() async {
    await _messaging.requestPermission(alert: true, badge: true, sound: true);
  }

  void _registerOnTokenRefresh() {
    _messaging.onTokenRefresh.listen(_registerToken);
  }

  Future<void> _registerToken(String token) async {
    final hasToken = await _authService.hasToken();
    if (!hasToken) return;

    final platform = Platform.isIOS ? 'ios' : 'android';
    // Token registration failures are non-fatal (push is optional) but must
    // not be silently swallowed, or the user is quietly unsubscribed.
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
