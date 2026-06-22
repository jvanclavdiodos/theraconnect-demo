import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../models/assessment.dart';
import '../../providers/assessment_provider.dart';

/// "My questionnaires" — the patient's PHQ-9 / GAD-7 assignments: pending ones
/// to complete (call-to-action) and a history of completed scores.
class AssessmentsScreen extends ConsumerWidget {
  const AssessmentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final assessmentsAsync = ref.watch(assessmentsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('My questionnaires')),
      body: assessmentsAsync.when(
        data: (items) {
          if (items.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'Your clinician has not assigned any questionnaires yet.',
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }

          final pending = items.where((a) => a.isPending).toList();
          final completed = items.where((a) => !a.isPending).toList();

          return RefreshIndicator(
            onRefresh: () async => ref.refresh(assessmentsProvider.future),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (pending.isNotEmpty) ...[
                  Text('To complete',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  ...pending.map((a) => _PendingCard(assessment: a)),
                  const SizedBox(height: 16),
                ],
                if (completed.isNotEmpty) ...[
                  Text('History',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  ...completed.map((a) => _CompletedCard(assessment: a)),
                ],
              ],
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) =>
            Center(child: Text(ApiError.fromException(e).userMessage)),
      ),
    );
  }
}

class _PendingCard extends StatelessWidget {
  final Assessment assessment;
  const _PendingCard({required this.assessment});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: const Icon(Icons.assignment_outlined),
        title: Text(assessment.title),
        subtitle: Text(assessment.name ?? 'Tap to complete'),
        trailing: FilledButton(
          onPressed: () => context.push('/assessments/${assessment.id}'),
          child: const Text('Start'),
        ),
      ),
    );
  }
}

class _CompletedCard extends StatelessWidget {
  final Assessment assessment;
  const _CompletedCard({required this.assessment});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: severityColor(assessment.severity).withValues(alpha: 0.15),
          child: Text(
            '${assessment.score ?? '—'}',
            style: TextStyle(
                color: severityColor(assessment.severity),
                fontWeight: FontWeight.bold),
          ),
        ),
        title: Text(assessment.title),
        subtitle: Text([
          if (assessment.severity != null) assessment.severity!,
          if (assessment.completedAt != null)
            assessment.completedAt!.split('T').first,
        ].join(' · ')),
        trailing: assessment.max != null
            ? Text('${assessment.score}/${assessment.max}',
                style: Theme.of(context).textTheme.bodySmall)
            : null,
      ),
    );
  }
}

/// Maps a severity band to a color (greener = better, redder = worse).
Color severityColor(String? severity) {
  switch (severity) {
    case 'Minimal':
      return Colors.green;
    case 'Mild':
      return Colors.lightGreen.shade700;
    case 'Moderate':
      return Colors.orange;
    case 'Moderately severe':
      return Colors.deepOrange;
    case 'Severe':
      return Colors.red;
    default:
      return Colors.blueGrey;
  }
}
