import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'providers/auth_provider.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/shell/home_shell.dart';
import 'screens/dashboard/dashboard_screen.dart';
import 'models/clinician.dart';
import 'screens/schedule/schedule_screen.dart';
import 'screens/schedule/calendar_screen.dart';
import 'screens/schedule/book_appointment_screen.dart';
import 'screens/appointments/appointment_list_screen.dart';
import 'screens/appointments/appointment_detail_screen.dart';
import 'screens/assignments/assignment_list_screen.dart';
import 'screens/assignments/assignment_detail_screen.dart';
import 'screens/assignments/submit_assignment_screen.dart';
import 'screens/messages/inbox_screen.dart';
import 'screens/messages/message_thread_screen.dart';
import 'screens/chatbot/chatbot_screen.dart';
import 'screens/notifications/notification_list_screen.dart';
import 'screens/profile/profile_screen.dart';
import 'screens/profile/edit_profile_screen.dart';
import 'screens/downloads/downloads_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();

/// Bridges Riverpod's auth state changes to GoRouter's `refreshListenable`.
///
/// GoRouter expects a `Listenable`; when `notifyListeners` is called, it re-
/// evaluates the redirect callback. The legacy pattern was a top-level global
/// `ValueNotifier<bool>` that was *toggled* on every auth state change —
/// fragile because (a) the toggle value carries no semantic meaning and
/// (b) top-level globals outlive the router provider's lifetime.
///
/// This adapter is owned by `routerProvider`: each provider instance gets
/// its own adapter + its own Riverpod subscription. Disposal of the provider
/// also tears down the subscription (via Riverpod's ref.listen auto-unlisten),
/// leaving no dangling listeners between HMR refreshes.
class _GoRouterRefreshAuth extends ChangeNotifier {}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _GoRouterRefreshAuth();

  // Whenever auth state changes (login, logout, token expiry via
  // _handleUnauthorized, etc.), tell GoRouter to re-run redirect.
  ref.listen(authProvider, (_, __) {
    refresh.notifyListeners();
  });

  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    refreshListenable: refresh,
    initialLocation:
        ref.read(authProvider).status == AuthState.authenticated ? '/dashboard' : '/login',
    redirect: (context, state) {
      final authStatus = ref.read(authProvider).status;
      final isAuthRoute =
          state.matchedLocation == '/login' || state.matchedLocation == '/register';

      if (authStatus == AuthState.authenticated && isAuthRoute) {
        return '/dashboard';
      }
      if (authStatus == AuthState.unauthenticated && !isAuthRoute) {
        return '/login';
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return HomeShell(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/dashboard',
                builder: (context, state) => const DashboardScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/schedule',
                builder: (context, state) => const ScheduleScreen(),
              ),
              GoRoute(
                path: '/schedule/calendar',
                builder: (context, state) {
                  final clinician = state.extra;
                  // Reached without a clinician (deep link / restart) → restart flow.
                  if (clinician is! Clinician) return const ScheduleScreen();
                  return CalendarScreen(clinician: clinician);
                },
              ),
              GoRoute(
                path: '/schedule/book',
                builder: (context, state) => const BookAppointmentScreen(),
              ),
              GoRoute(
                path: '/appointments',
                builder: (context, state) => const AppointmentListScreen(),
              ),
              GoRoute(
                path: '/appointments/:id',
                builder: (context, state) {
                  final id = int.parse(state.pathParameters['id']!);
                  return AppointmentDetailScreen(appointmentId: id);
                },
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/assignments',
                builder: (context, state) => const AssignmentListScreen(),
              ),
              GoRoute(
                path: '/assignments/:id',
                builder: (context, state) {
                  final id = int.parse(state.pathParameters['id']!);
                  return AssignmentDetailScreen(assignmentId: id);
                },
              ),
              GoRoute(
                path: '/assignments/:id/submit',
                builder: (context, state) {
                  final id = int.parse(state.pathParameters['id']!);
                  return SubmitAssignmentScreen(assignmentId: id);
                },
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/messages',
                builder: (context, state) => const InboxScreen(),
              ),
              GoRoute(
                path: '/messages/:id',
                builder: (context, state) {
                  final id = int.parse(state.pathParameters['id']!);
                  final title = state.extra is String ? state.extra as String : null;
                  return MessageThreadScreen(conversationId: id, title: title);
                },
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/chatbot',
                builder: (context, state) => const ChatbotScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/profile',
                builder: (context, state) => const ProfileScreen(),
              ),
              GoRoute(
                path: '/profile/edit',
                builder: (context, state) => const EditProfileScreen(),
              ),
              GoRoute(
                path: '/notifications',
                builder: (context, state) => const NotificationListScreen(),
              ),
              GoRoute(
                path: '/downloads',
                builder: (context, state) => const DownloadsScreen(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
});
