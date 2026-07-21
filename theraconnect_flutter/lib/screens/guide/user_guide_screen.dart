import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/user_guide.dart';
import '../../providers/user_guide_provider.dart';

class UserGuideScreen extends ConsumerWidget {
  const UserGuideScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final guide = ref.watch(userGuideProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('User Guide')),
      body: guide.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, __) => Center(
            child: FilledButton.icon(
                onPressed: () => ref.invalidate(userGuideProvider),
                icon: const Icon(Icons.refresh),
                label: const Text('Try again'))),
        data: (sections) => ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: sections.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (context, index) =>
              _GuideSectionCard(section: sections[index]),
        ),
      ),
    );
  }
}

class _GuideSectionCard extends StatelessWidget {
  final UserGuideSection section;
  const _GuideSectionCard({required this.section});

  @override
  Widget build(BuildContext context) => Card(
          child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(section.title,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text(section.description),
          const SizedBox(height: 12),
          TextButton.icon(
              onPressed: () => context.push(_routeFor(section.action)),
              icon: const Icon(Icons.arrow_forward),
              label: const Text('Open section')),
        ]),
      ));

  String _routeFor(String action) => switch (action) {
        'appointments' => '/appointments',
        'messages' => '/messages',
        'assignments' => '/assignments',
        'progress' => '/progress',
        'profile' => '/profile',
        _ => '/dashboard',
      };
}
