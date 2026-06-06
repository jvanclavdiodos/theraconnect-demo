import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/assignment_provider.dart';

class AssignmentDetailScreen extends ConsumerWidget {
  final int assignmentId;

  const AssignmentDetailScreen({super.key, required this.assignmentId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(assignmentDetailProvider(assignmentId));

    return detailAsync.when(
      data: (a) => _buildContent(context, a),
      loading: () => Scaffold(
        appBar: AppBar(title: const Text('Assignment')),
        body: const Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: const Text('Assignment')),
        body: Center(child: Text('$e')),
      ),
    );
  }

  Widget _buildContent(BuildContext context, assignment) {
    return Scaffold(
      appBar: AppBar(title: const Text('Assignment')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(assignment.title, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Chip(
                        label: Text(
                          assignment.isReviewed
                              ? 'Reviewed'
                              : assignment.isSubmitted
                                  ? 'Submitted'
                                  : 'Pending',
                          style: const TextStyle(fontSize: 12),
                        ),
                      ),
                      const SizedBox(width: 8),
                      if (assignment.dueDate != null)
                        Chip(
                          label: Text('Due: ${assignment.dueDate}', style: const TextStyle(fontSize: 12)),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          if (assignment.description != null && assignment.description!.isNotEmpty) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Description', style: Theme.of(context).textTheme.titleMedium),
                    const Divider(),
                    Text(assignment.description!),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Status', style: Theme.of(context).textTheme.titleMedium),
                  const Divider(),
                  Row(children: [
                    Expanded(child: Text('Submitted', style: Theme.of(context).textTheme.bodyMedium)),
                    Icon(assignment.isSubmitted ? Icons.check_circle : Icons.cancel,
                        color: assignment.isSubmitted ? Colors.green : Colors.grey),
                  ]),
                  const SizedBox(height: 4),
                  Row(children: [
                    Expanded(child: Text('Reviewed', style: Theme.of(context).textTheme.bodyMedium)),
                    Icon(assignment.isReviewed ? Icons.check_circle : Icons.cancel,
                        color: assignment.isReviewed ? Colors.green : Colors.grey),
                  ]),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          if (!assignment.isReviewed)
            FilledButton.icon(
              onPressed: () => context.push('/assignments/${assignment.id}/submit'),
              icon: const Icon(Icons.upload),
              label: Text(assignment.isSubmitted ? 'Re-submit' : 'Submit'),
              style: FilledButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
            ),
        ],
      ),
    );
  }
}
