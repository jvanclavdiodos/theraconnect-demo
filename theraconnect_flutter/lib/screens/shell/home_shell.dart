import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/message_provider.dart';
import '../../widgets/joy_floating_chat_head.dart';

class HomeShell extends ConsumerWidget {
  final StatefulNavigationShell navigationShell;

  const HomeShell({super.key, required this.navigationShell});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isMessaging =
        GoRouterState.of(context).uri.path.startsWith('/messages');
    final unreadMessages = ref.watch(conversationsProvider).maybeWhen(
          data: (snapshot) => snapshot.conversations.fold<int>(
            0,
            (total, conversation) => total + conversation.unreadCount,
          ),
          orElse: () => 0,
        );

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(child: navigationShell),
          Positioned.fill(
            child: JoyFloatingChatHead(
              visible: !isMessaging,
              onOpen: () => context.push('/chatbot'),
            ),
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: (index) {
          navigationShell.goBranch(
            index,
            initialLocation: index == navigationShell.currentIndex,
          );
        },
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home),
            selectedIcon: Icon(Icons.home_filled),
            label: 'Home',
          ),
          const NavigationDestination(
            icon: Icon(Icons.calendar_today),
            selectedIcon: Icon(Icons.calendar_today),
            label: 'Schedule',
          ),
          const NavigationDestination(
            icon: Icon(Icons.assignment),
            selectedIcon: Icon(Icons.assignment),
            label: 'Assignments',
          ),
          NavigationDestination(
            icon: _MessagesIcon(
              icon: Icons.forum_outlined,
              unreadCount: unreadMessages,
            ),
            selectedIcon: _MessagesIcon(
              icon: Icons.forum,
              unreadCount: unreadMessages,
            ),
            label: 'Messages',
          ),
        ],
      ),
    );
  }
}

class _MessagesIcon extends StatelessWidget {
  final IconData icon;
  final int unreadCount;

  const _MessagesIcon({required this.icon, required this.unreadCount});

  @override
  Widget build(BuildContext context) {
    final messageIcon = Icon(icon);
    if (unreadCount <= 0) return messageIcon;

    return Badge(
      backgroundColor: Theme.of(context).colorScheme.error,
      textColor: Theme.of(context).colorScheme.onError,
      label: Text(unreadCount > 99 ? '99+' : '$unreadCount'),
      child: messageIcon,
    );
  }
}
