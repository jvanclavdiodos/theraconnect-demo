import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/assignment_provider.dart';
import '../../theme/app_theme.dart';

class AssignmentListScreen extends ConsumerStatefulWidget {
  const AssignmentListScreen({super.key});

  @override
  ConsumerState<AssignmentListScreen> createState() =>
      _AssignmentListScreenState();
}

class _AssignmentListScreenState extends ConsumerState<AssignmentListScreen> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;

  Future<void> _load() => ref
      .read(assignmentsProvider.notifier)
      .loadAssignments(search: _searchController.text);

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), _load);
    setState(() {});
  }

  void _clearSearch() {
    _searchDebounce?.cancel();
    _searchController.clear();
    setState(() {});
    _load();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final assignments = ref.watch(assignmentsProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Assignments')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                labelText: 'Search assignments',
                hintText: 'Title, details, or clinician',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchController.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: _clearSearch,
                        tooltip: 'Clear search',
                        icon: const Icon(Icons.close),
                      ),
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: assignments.when(
                data: (data) {
                  if (data.isEmpty) {
                    return ListView(
                      children: [
                        SizedBox(
                            height: MediaQuery.of(context).size.height * 0.3),
                        Center(
                          child: Column(
                            children: [
                              Icon(Icons.assignment_outlined,
                                  size: 64, color: scheme.onSurfaceVariant),
                              const SizedBox(height: 16),
                              Text(
                                _searchController.text.trim().isEmpty
                                    ? 'No assignments yet'
                                    : 'No assignments match your search',
                                style: const TextStyle(fontSize: 16),
                              ),
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
                        margin: const EdgeInsets.symmetric(
                            vertical: 4, horizontal: 8),
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
                          subtitle: Text(a.dueDate != null
                              ? 'Due: ${a.dueDate}'
                              : 'No due date'),
                          trailing: Chip(
                            label: Text(
                              a.isReviewed
                                  ? 'Reviewed'
                                  : a.isSubmitted
                                      ? 'Submitted'
                                      : 'Pending',
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
                error: (e, _) =>
                    Center(child: Text(ApiError.fromException(e).userMessage)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
