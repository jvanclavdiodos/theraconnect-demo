import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'providers/auth_provider.dart';
import 'providers/appointment_provider.dart';
import 'providers/assignment_provider.dart';
import 'providers/notification_provider.dart';
import 'providers/profile_provider.dart';
import 'services/fcm_service.dart';
import 'router.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();

  try {
    await Firebase.initializeApp();
  } catch (_) {}

  runApp(
    ProviderScope(
      overrides: [
        sharedPreferencesProvider.overrideWithValue(prefs),
      ],
      child: const TheraConnectApp(),
    ),
  );
}

class TheraConnectApp extends ConsumerStatefulWidget {
  const TheraConnectApp({super.key});

  @override
  ConsumerState<TheraConnectApp> createState() => _TheraConnectAppState();
}

class _TheraConnectAppState extends ConsumerState<TheraConnectApp> {
  @override
  void initState() {
    super.initState();
    _initializeData();
  }

  Future<void> _initializeData() async {
    final apiClient = ref.read(apiClientProvider);
    ref.read(authProvider.notifier).setApiClient(apiClient);

    await ref.read(authProvider.notifier).checkAuth();

    final authState = ref.read(authProvider);
    if (authState.status == AuthState.authenticated) {
      final loaders = [
        ref.read(appointmentsProvider.notifier).loadAppointments(),
        ref.read(assignmentsProvider.notifier).loadAssignments(),
        ref.read(notificationsProvider.notifier).loadNotifications(),
        ref.read(profileProvider.notifier).loadProfile(),
        _initFcmIfAvailable(),
      ];
      await Future.wait(loaders);
    }
  }

  Future<void> _initFcmIfAvailable() async {
    try {
      final fcmService = FcmService(
        notificationApi: ref.read(notificationApiProvider),
        authService: ref.read(authServiceProvider),
      );
      await fcmService.initialize();
    } catch (e) {
      debugPrint('FCM init failed: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'TheraConnect',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorSchemeSeed: Colors.indigo,
        useMaterial3: true,
        brightness: Brightness.light,
      ),
      routerConfig: router,
    );
  }
}
