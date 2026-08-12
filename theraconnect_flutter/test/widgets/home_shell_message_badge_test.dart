import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:theraconnect/models/conversation.dart';
import 'package:theraconnect/providers/message_provider.dart';
import 'package:theraconnect/screens/shell/home_shell.dart';

void main() {
  testWidgets('messages destination displays the total unread count',
      (tester) async {
    late final GoRouter router;
    router = GoRouter(
      initialLocation: '/dashboard',
      routes: [
        StatefulShellRoute.indexedStack(
          builder: (_, __, shell) => HomeShell(navigationShell: shell),
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                    path: '/dashboard', builder: (_, __) => const SizedBox())
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(path: '/schedule', builder: (_, __) => const SizedBox())
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                    path: '/assignments', builder: (_, __) => const SizedBox())
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(path: '/messages', builder: (_, __) => const SizedBox())
              ],
            ),
          ],
        ),
        GoRoute(path: '/chatbot', builder: (_, __) => const SizedBox()),
      ],
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          conversationsProvider.overrideWith((ref) async {
            return const ConversationSnapshot([
              Conversation(id: 'one', unreadCount: 2),
              Conversation(id: 'two', unreadCount: 3),
            ]);
          }),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('5'), findsOneWidget);
    expect(find.text('Messages'), findsOneWidget);

    router.dispose();
  });
}
