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
        data: (sections) => LayoutBuilder(
          builder: (context, constraints) {
            final horizontalPadding = constraints.maxWidth < 480 ? 12.0 : 24.0;
            return Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 760),
                child: ListView.separated(
                  padding: EdgeInsets.fromLTRB(
                    horizontalPadding,
                    16,
                    horizontalPadding,
                    32,
                  ),
                  itemCount: sections.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) => _GuideSectionCard(
                    number: index + 1,
                    section: sections[index],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _GuideSectionCard extends StatelessWidget {
  final int number;
  final UserGuideSection section;
  const _GuideSectionCard({required this.number, required this.section});

  @override
  Widget build(BuildContext context) => Card(
        clipBehavior: Clip.antiAlias,
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          leading: CircleAvatar(
            radius: 18,
            child: Text('$number'),
          ),
          title: Text(section.title,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.bold)),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(section.description),
          ),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 18),
          expandedCrossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (section.beforeYouStart != null) ...[
              const SizedBox(height: 8),
              _InformationBlock(
                icon: Icons.info_outline,
                title: 'Before you start',
                body: section.beforeYouStart!,
              ),
            ],
            const SizedBox(height: 16),
            Text('Steps', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            for (var index = 0; index < section.steps.length; index++)
              _StepRow(number: index + 1, text: section.steps[index]),
            const SizedBox(height: 12),
            _InformationBlock(
              icon: Icons.check_circle_outline,
              title: 'Expected result',
              body: section.expectedResult,
            ),
            if (section.tips.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('Important reminders',
                  style: Theme.of(context).textTheme.titleSmall),
              const SizedBox(height: 6),
              for (final tip in section.tips)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Padding(
                        padding: EdgeInsets.only(top: 2, right: 8),
                        child: Icon(Icons.lightbulb_outline, size: 18),
                      ),
                      Expanded(child: Text(tip)),
                    ],
                  ),
                ),
            ],
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                  onPressed: () => context.push(_routeFor(section.action)),
                  icon: const Icon(Icons.arrow_forward),
                  label: const Text('Open this section')),
            ),
          ],
        ),
      );

  String _routeFor(String action) => switch (action) {
        'appointments' => '/appointments',
        'messages' => '/messages',
        'assignments' => '/assignments',
        'progress' => '/progress',
        'notifications' => '/notifications',
        'profile' => '/profile',
        _ => '/dashboard',
      };
}

class _StepRow extends StatelessWidget {
  final int number;
  final String text;

  const _StepRow({required this.number, required this.text});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 26,
              height: 26,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: Text('$number',
                  style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
            const SizedBox(width: 10),
            Expanded(child: Text(text)),
          ],
        ),
      );
}

class _InformationBlock extends StatelessWidget {
  final IconData icon;
  final String title;
  final String body;

  const _InformationBlock(
      {required this.icon, required this.title, required this.body});

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: const TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 3),
                  Text(body),
                ],
              ),
            ),
          ],
        ),
      );
}
