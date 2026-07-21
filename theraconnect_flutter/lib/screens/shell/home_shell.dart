import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../widgets/joy_avatar.dart';

class HomeShell extends ConsumerWidget {
  final StatefulNavigationShell navigationShell;

  const HomeShell({super.key, required this.navigationShell});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isMessaging =
        GoRouterState.of(context).uri.path.startsWith('/messages');

    return Scaffold(
      body: navigationShell,
      floatingActionButton: isMessaging
          ? null
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Material(
                  elevation: 4,
                  color: Theme.of(context).colorScheme.surface,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                    side: BorderSide(
                        color: Theme.of(context).colorScheme.primary),
                  ),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: () => context.push('/chatbot'),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 10),
                      child: Text.rich(
                        TextSpan(
                          text: 'Have questions? ',
                          children: [
                            TextSpan(
                              text: 'Talk to Joy.',
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.primary,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                FloatingActionButton(
                  heroTag: 'joy-assistant',
                  tooltip: 'Open Joy assistant',
                  onPressed: () => context.push('/chatbot'),
                  child: const JoyAvatar(size: 30),
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
          const NavigationDestination(
            icon: Icon(Icons.forum_outlined),
            selectedIcon: Icon(Icons.forum),
            label: 'Messages',
          ),
        ],
      ),
    );
  }
}
