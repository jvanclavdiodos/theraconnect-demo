import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:file_picker/file_picker.dart';
import '../../models/api_response.dart';
import '../../models/assignment.dart';
import '../../providers/assignment_provider.dart';
import '../../theme/app_theme.dart';

class SubmitAssignmentScreen extends ConsumerStatefulWidget {
  final int assignmentId;

  const SubmitAssignmentScreen({super.key, required this.assignmentId});

  @override
  ConsumerState<SubmitAssignmentScreen> createState() => _SubmitAssignmentScreenState();
}

class _SubmitAssignmentScreenState extends ConsumerState<SubmitAssignmentScreen> {
  final _contentController = TextEditingController();
  String? _filePath;
  String? _fileName;
  bool _submitting = false;

  @override
  void dispose() {
    _contentController.dispose();
    super.dispose();
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles();
    if (result != null && result.files.isNotEmpty) {
      setState(() {
        _filePath = result.files.first.path;
        _fileName = result.files.first.name;
      });
    }
  }

  Future<void> _submit() async {
    final content = _contentController.text.trim();
    final colorScheme = Theme.of(context).colorScheme;

    if (content.isEmpty && _filePath == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: const Text('Please provide text or a file.'), backgroundColor: colorScheme.error),
      );
      return;
    }

    setState(() => _submitting = true);

    final error = await ref.read(assignmentsProvider.notifier).submitAssignment(
          widget.assignmentId,
          content: content.isEmpty ? null : content,
          filePath: _filePath,
        );

    if (mounted) {
      setState(() => _submitting = false);
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error), backgroundColor: colorScheme.error),
        );
      } else {
        ref.invalidate(assignmentDetailProvider(widget.assignmentId));
        ref.read(assignmentsProvider.notifier).loadAssignments();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Assignment submitted!'), backgroundColor: AppTheme.success),
        );
        context.pop();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(assignmentDetailProvider(widget.assignmentId));

    return detailAsync.when(
      data: (a) => _buildContent(context, a),
      loading: () => Scaffold(
        appBar: AppBar(title: const Text('Submit Assignment')),
        body: const Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: const Text('Submit Assignment')),
        body: Center(child: Text(ApiError.fromException(e).userMessage)),
      ),
    );
  }

  Widget _buildContent(BuildContext context, Assignment assignment) {
    return Scaffold(
      appBar: AppBar(title: const Text('Submit Assignment')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(assignment.title, style: Theme.of(context).textTheme.titleLarge),
                  if (assignment.dueDate != null) ...[
                    const SizedBox(height: 4),
                    Text('Due: ${assignment.dueDate}'),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _contentController,
            maxLines: 8,
            decoration: const InputDecoration(
              labelText: 'Your response',
              border: OutlineInputBorder(),
              hintText: 'Write your answer here...',
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _pickFile,
            icon: const Icon(Icons.attach_file),
            label: Text(_fileName ?? 'Attach file (optional)'),
            style: OutlinedButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            style: FilledButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
            child: _submitting
                ? CircularProgressIndicator(strokeWidth: 2, color: Theme.of(context).colorScheme.onPrimary)
                : const Text('Submit Assignment'),
          ),
        ],
      ),
    );
  }
}
