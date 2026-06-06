import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'providers/auth_provider.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/shell/home_shell.dart';
import 'screens/dashboard/dashboard_screen.dart';
import 'screens/schedule/schedule_screen.dart';
import 'screens/schedule/book_appointment_screen.dart';
import 'screens/appointments/appointment_list_screen.dart';
import 'screens/appointments/appointment_detail_screen.dart';
import 'screens/assignments/assignment_list_screen.dart';
import 'screens/assignments/assignment_detail_screen.dart';
import 'screens/assignments/submit_assignment_screen.dart';
import 'screens/chatbot/chatbot_screen.dart';
import 'screens/notifications/notification_list_screen.dart';
import 'screens/profile/profile_screen.dart';
import 'screens/profile/edit_profile_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();
final _authNotifier = ValueNotifier<bool>(false);

final routerProvider = Provider<GoRouter>((ref) {
  ref.listen(authProvider, (_, __) {
    _authNotifier.value = !_authNotifier.value;
  });

  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    refreshListenable: _authNotifier,
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
            ],
          ),
        ],
      ),
    ],
  );
});
