import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:media_store_plus/media_store_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'l10n/app_localizations.dart';
import 'providers/auth_provider.dart';
import 'providers/appointment_provider.dart';
import 'providers/assignment_provider.dart';
import 'providers/message_provider.dart';
import 'providers/notification_provider.dart';
import 'providers/profile_provider.dart';
import 'providers/realtime_provider.dart';
import 'providers/theme_provider.dart';
import 'services/fcm_service.dart';
import 'services/realtime_service.dart';
import 'theme/app_theme.dart';
import 'router.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();

  // Worksheets are saved into Download/TheraConnect via MediaStore. The plugin
  // must be initialized once and the app folder set before any saveFile call.
  MediaStore.appFolder = 'TheraConnect';
  try {
    await MediaStore.ensureInitialized();
  } catch (_) {}

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
  StreamSubscription<RealtimeEvent>? _realtimeSubscription;

  @override
  void initState() {
    super.initState();
    _realtimeSubscription =
        ref.read(realtimeServiceProvider).events.listen(_handleRealtimeEvent);
    _initializeData();
  }

  @override
  void dispose() {
    _realtimeSubscription?.cancel();
    super.dispose();
  }

  Future<void> _initializeData() async {
    final apiClient = ref.read(apiClientProvider);
    ref.read(authProvider.notifier).setApiClient(apiClient);

    // checkAuth() flips auth state to authenticated when a saved token is
    // valid; the ref.listen in build() then loads the user's data. Loading is
    // driven off the auth transition so it also fires for interactive login —
    // the old startup-only load left list screens spinning forever after a
    // fresh sign-in (no token at launch).
    await ref.read(authProvider.notifier).checkAuth();
  }

  void _loadUserData() {
    ref.read(appointmentsProvider.notifier).loadAppointments();
    ref.read(assignmentsProvider.notifier).loadAssignments();
    ref.read(notificationsProvider.notifier).loadNotifications();
    ref.read(profileProvider.notifier).loadProfile();
    final user = ref.read(authProvider).user;
    if (user != null) {
      unawaited(ref.read(realtimeServiceProvider).connect(user.id));
    }
    _initFcmIfAvailable();
  }

  Future<void> _handleRealtimeEvent(RealtimeEvent event) async {
    switch (event.name) {
      case 'notification.created':
        await ref.read(notificationsProvider.notifier).loadNotifications();
        if (event.data['type'] == 'message_received') {
          ref.invalidate(conversationsProvider);
        }
        break;
      case 'appointment.updated':
        await Future.wait([
          ref.read(appointmentsProvider.notifier).loadAppointments(),
          ref.read(appointmentListProvider.notifier).loadAppointments(),
        ]);
        final appointmentId = event.data['appointment_id'];
        if (appointmentId is int) {
          ref.invalidate(appointmentDetailProvider(appointmentId));
        }
        break;
      case 'connected':
        await Future.wait([
          ref.read(notificationsProvider.notifier).loadNotifications(),
          ref.read(appointmentsProvider.notifier).loadAppointments(),
          ref.read(appointmentListProvider.notifier).loadAppointments(),
        ]);
        ref.invalidate(conversationsProvider);
        break;
    }
  }

  Future<void> _initFcmIfAvailable() async {
    try {
      final fcmService = FcmService(
        notificationApi: ref.read(notificationApiProvider),
        authService: ref.read(authServiceProvider),
      );
      final router = ref.read(routerProvider);
      await fcmService.initialize(
        // Tapping a push deep-links to the relevant screen.
        onNavigate: (route) => router.go(route),
        // A push arriving in the foreground refreshes the in-app list.
        onForegroundRefresh: () async {
          await Future.wait([
            ref.read(notificationsProvider.notifier).loadNotifications(),
            ref.read(appointmentsProvider.notifier).loadAppointments(),
            ref.read(appointmentListProvider.notifier).loadAppointments(),
          ]);
          ref.invalidate(conversationsProvider);
        },
      );
    } catch (e) {
      debugPrint('FCM init failed: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    // Load the user's data whenever they become authenticated — covers both
    // app launch with a saved token (via checkAuth) and interactive login.
    ref.listen(authProvider, (prev, next) {
      if (prev?.status != AuthState.authenticated &&
          next.status == AuthState.authenticated) {
        _loadUserData();
      }
      if (prev?.status == AuthState.authenticated &&
          next.status == AuthState.unauthenticated) {
        unawaited(ref.read(realtimeServiceProvider).disconnect());
      }
    });

    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'TheraConnect',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      // themeModeProvider persists the user's choice (light/dark/system) in
      // SharedPreferences; defaults to system so the device preference applies
      // until the user explicitly changes it in Profile → Appearance.
      themeMode: ref.watch(themeModeProvider),
      // i18n: AppLocalizations is generated by `flutter gen-l10n` from
      // lib/l10n/app_*.arb. Falls back to English when the device locale has
      // no corresponding .arb file yet (only en today — add more .arb files
      // to extend coverage). See l10n.yaml at the project root.
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: router,
    );
  }
}
