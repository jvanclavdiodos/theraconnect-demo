import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:open_filex/open_filex.dart';
import '../../models/assignment.dart';
import '../../models/api_response.dart';
import '../../providers/assignment_provider.dart';
import '../../providers/download_provider.dart';
import '../../theme/app_theme.dart';
import 'submission_preview.dart';

class AssignmentDetailScreen extends ConsumerStatefulWidget {
  final int assignmentId;

  const AssignmentDetailScreen({super.key, required this.assignmentId});

  @override
  ConsumerState<AssignmentDetailScreen> createState() =>
      _AssignmentDetailScreenState();
}

class _AssignmentDetailScreenState
    extends ConsumerState<AssignmentDetailScreen> {
  bool _downloading = false;

  Future<void> _downloadWorksheet(Assignment a) async {
    final colorScheme = Theme.of(context).colorScheme;
    setState(() => _downloading = true);
    try {
      final fileName = (a.attachmentName == null || a.attachmentName!.isEmpty)
          ? 'worksheet'
          : a.attachmentName!;
      final downloaded = await ref
          .read(assignmentApiProvider)
          .downloadWorksheet(a.id, fileName, a.title);
      // Reflect the new file in the in-app Downloads list immediately.
      ref.read(downloadsProvider.notifier).refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Saved to Downloads/TheraConnect'),
            backgroundColor: AppTheme.success,
          ),
        );
      }
      final result = await OpenFilex.open(downloaded.localPath);
      if (result.type != ResultType.done && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Saved, but could not open it: ${result.message}'),
            backgroundColor: AppTheme.warning,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        // Collapse any non-ApiError exception to a patient-friendly message —
        // never leak backend paths / Dio internals / stack traces to patients.
        final msg = ApiError.fromException(e).userMessage;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Download failed: $msg'),
            backgroundColor: colorScheme.error,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync =
        ref.watch(assignmentDetailProvider(widget.assignmentId));

    return detailAsync.when(
      data: (a) => _buildContent(context, a),
      loading: () => Scaffold(
        appBar: AppBar(title: const Text('Assignment')),
        body: const Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: const Text('Assignment')),
        body: Center(child: Text(ApiError.fromException(e).userMessage)),
      ),
    );
  }

  Widget _buildContent(BuildContext context, Assignment assignment) {
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
                  Text(assignment.title,
                      style: Theme.of(context).textTheme.headlineSmall),
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
                          label: Text('Due: ${assignment.dueDate}',
                              style: const TextStyle(fontSize: 12)),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          if (assignment.description != null &&
              assignment.description!.isNotEmpty) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Description',
                        style: Theme.of(context).textTheme.titleMedium),
                    const Divider(),
                    Text(assignment.description!),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          if (assignment.hasAttachment) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Worksheet',
                        style: Theme.of(context).textTheme.titleMedium),
                    const Divider(),
                    Row(
                      children: [
                        const Icon(Icons.description_outlined),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            assignment.attachmentName ?? 'Attachment',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    FilledButton.tonalIcon(
                      onPressed: _downloading
                          ? null
                          : () => _downloadWorksheet(assignment),
                      icon: _downloading
                          ? const SizedBox(
                              height: 18,
                              width: 18,
                              child:
                                  CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.download),
                      label: Text(
                          _downloading ? 'Downloading…' : 'Download worksheet'),
                      style: FilledButton.styleFrom(
                          minimumSize: const Size(double.infinity, 44)),
                    ),
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
                  Text('Status',
                      style: Theme.of(context).textTheme.titleMedium),
                  const Divider(),
                  Row(children: [
                    Expanded(
                        child: Text('Submitted',
                            style: Theme.of(context).textTheme.bodyMedium)),
                    Icon(
                        assignment.isSubmitted
                            ? Icons.check_circle
                            : Icons.cancel,
                        color: assignment.isSubmitted
                            ? AppTheme.green
                            : Theme.of(context).colorScheme.onSurfaceVariant),
                  ]),
                  const SizedBox(height: 4),
                  Row(children: [
                    Expanded(
                        child: Text('Reviewed',
                            style: Theme.of(context).textTheme.bodyMedium)),
                    Icon(
                        assignment.isReviewed
                            ? Icons.check_circle
                            : Icons.cancel,
                        color: assignment.isReviewed
                            ? AppTheme.green
                            : Theme.of(context).colorScheme.onSurfaceVariant),
                  ]),
                ],
              ),
            ),
          ),
          if (assignment.submission != null) ...[
            const SizedBox(height: 16),
            SubmissionPreview(submission: assignment.submission!),
          ],
          const SizedBox(height: 24),
          if (!assignment.isReviewed)
            FilledButton.icon(
              onPressed: () =>
                  context.push('/assignments/${assignment.id}/submit'),
              icon: const Icon(Icons.upload),
              label: Text(assignment.isSubmitted ? 'Re-submit' : 'Submit'),
              style: FilledButton.styleFrom(
                  minimumSize: const Size(double.infinity, 48)),
            ),
        ],
      ),
    );
  }
}
