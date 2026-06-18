import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/assignment_provider.dart';

class AssignmentListScreen extends ConsumerWidget {
  const AssignmentListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final assignments = ref.watch(assignmentsProvider);

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
                  const Center(
                    child: Column(
                      children: [
                        Icon(Icons.assignment_outlined, size: 64, color: Colors.grey),
                        SizedBox(height: 16),
                        Text('No assignments yet', style: TextStyle(fontSize: 16)),
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
                          ? Colors.green.withOpacity(0.15)
                          : a.isSubmitted
                              ? Colors.orange.withOpacity(0.15)
                              : Theme.of(context).colorScheme.primaryContainer,
                      child: Icon(
                        a.isReviewed
                            ? Icons.check_circle
                            : a.isSubmitted
                                ? Icons.pending
                                : Icons.assignment,
                        color: a.isReviewed
                            ? Colors.green
                            : a.isSubmitted
                                ? Colors.orange
                                : Theme.of(context).colorScheme.onPrimaryContainer,
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
