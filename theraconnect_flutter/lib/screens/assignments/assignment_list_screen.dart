import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/assignment_provider.dart';
import '../../theme/app_theme.dart';

class AssignmentListScreen extends ConsumerWidget {
  const AssignmentListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final assignments = ref.watch(assignmentsProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Assignments')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(assignmentsProvider.notifier).loadAssignments(),
        child: assignments.when(
          data: (data) {
            if (data.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: MediaQuery.of(context).size.height * 0.3),
                  Center(
                    child: Column(
                      children: [
                        Icon(Icons.assignment_outlined, size: 64, color: scheme.onSurfaceVariant),
                        const SizedBox(height: 16),
                        const Text('No assignments yet', style: TextStyle(fontSize: 16)),
                      ],
                    ),
                  ),
                ],
              );
            }
            return ListView.builder(
              padding: const EdgeInsets.all(8),
              itemCount: data.length,
              itemBuilder: (context, index) {
                final a = data[index];
                return Card(
                  margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: a.isReviewed
                          ? AppTheme.green.withValues(alpha: 0.15)
                          : a.isSubmitted
                              ? AppTheme.amber.withValues(alpha: 0.15)
                              : scheme.primaryContainer,
                      child: Icon(
                        a.isReviewed
                            ? Icons.check_circle
                            : a.isSubmitted
                                ? Icons.pending
                                : Icons.assignment,
                        color: a.isReviewed
                            ? AppTheme.green
                            : a.isSubmitted
                                ? AppTheme.amber
                                : scheme.onPrimaryContainer,
                      ),
                    ),
                    title: Text(a.title),
                    subtitle: Text(a.dueDate != null ? 'Due: ${a.dueDate}' : 'No due date'),
                    trailing: Chip(
                      label: Text(
                        a.isReviewed ? 'Reviewed' : a.isSubmitted ? 'Submitted' : 'Pending',
                        style: const TextStyle(fontSize: 12),
                      ),
                    ),
                    onTap: () => context.push('/assignments/${a.id}'),
                  ),
                );
              },
            );
          },
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text(ApiError.fromException(e).userMessage)),
        ),
      ),
    );
  }
}
