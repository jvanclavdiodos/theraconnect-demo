import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import '../../models/api_response.dart';
import '../../models/assignment.dart';
import '../../providers/assignment_provider.dart';
import '../../providers/download_provider.dart';

/// "Your submission" card on the assignment detail screen. Shows the written
/// content plus a simple inline preview of the uploaded file (image/text) that
/// can be tapped to maximize. PDFs and other types open in the device viewer.
class SubmissionPreview extends ConsumerStatefulWidget {
  final AssignmentSubmission submission;

  const SubmissionPreview({super.key, required this.submission});

  @override
  ConsumerState<SubmissionPreview> createState() => _SubmissionPreviewState();
}

class _SubmissionPreviewState extends ConsumerState<SubmissionPreview> {
  Future<Uint8List>? _bytes;
  bool _opening = false;

  @override
  void initState() {
    super.initState();
    final s = widget.submission;
    // Pre-fetch bytes once for the kinds we render inline.
    if (s.hasFile && (s.kind == 'image' || s.kind == 'text')) {
      _bytes = ref.read(assignmentApiProvider).getSubmissionBytes(s.id);
    }
  }

  Future<void> _openExternally() async {
    final s = widget.submission;
    setState(() => _opening = true);
    try {
      final name = (s.originalName == null || s.originalName!.isEmpty)
          ? 'submission'
          : s.originalName!;
      final file = await ref
          .read(assignmentApiProvider)
          .downloadSubmission(s.id, name, name);
      ref.read(downloadsProvider.notifier).refresh();
      await OpenFilex.open(file.localPath);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiError.fromException(e).userMessage)),
        );
      }
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  void _maximizeImage(Uint8List bytes) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        insetPadding: const EdgeInsets.all(8),
        child: Stack(
          children: [
            InteractiveViewer(
              maxScale: 5,
              child: Center(child: Image.memory(bytes)),
            ),
            Positioned(
              top: 4,
              right: 4,
              child: IconButton.filledTonal(
                icon: const Icon(Icons.close),
                onPressed: () => Navigator.of(context).pop(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _maximizeText(String text) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        insetPadding: const EdgeInsets.all(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Flexible(
                child: SingleChildScrollView(
                  child: SelectableText(text),
                ),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.submission;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Your submission', style: Theme.of(context).textTheme.titleMedium),
            const Divider(),
            if (s.content != null && s.content!.isNotEmpty) ...[
              Text(s.content!),
              const SizedBox(height: 12),
            ],
            if (s.hasFile) _buildFilePreview(context, s),
          ],
        ),
      ),
    );
  }

  Widget _buildFilePreview(BuildContext context, AssignmentSubmission s) {
    if (s.kind == 'image') {
      return _bytesBuilder((bytes) => GestureDetector(
            onTap: () => _maximizeImage(bytes),
            child: Stack(
              alignment: Alignment.bottomRight,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.memory(bytes, height: 180, width: double.infinity, fit: BoxFit.cover),
                ),
                const Padding(
                  padding: EdgeInsets.all(6),
                  child: Chip(
                    avatar: Icon(Icons.fullscreen, size: 16),
                    label: Text('Tap to enlarge'),
                    visualDensity: VisualDensity.compact,
                  ),
                ),
              ],
            ),
          ));
    }

    if (s.kind == 'text') {
      return _bytesBuilder((bytes) {
        final text = utf8.decode(bytes, allowMalformed: true);
        final snippet = text.length > 240 ? '${text.substring(0, 240)}…' : text;
        return InkWell(
          onTap: () => _maximizeText(text),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              border: Border.all(color: Theme.of(context).dividerColor),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(snippet, style: const TextStyle(fontFamily: 'monospace')),
                const SizedBox(height: 6),
                Text('Tap to view full text',
                    style: Theme.of(context).textTheme.bodySmall
                        ?.copyWith(color: Theme.of(context).colorScheme.primary)),
              ],
            ),
          ),
        );
      });
    }

    // pdf / other → open in the device viewer (full screen there).
    return Row(
      children: [
        const Icon(Icons.description_outlined),
        const SizedBox(width: 8),
        Expanded(child: Text(s.originalName ?? 'Attachment', overflow: TextOverflow.ellipsis)),
        FilledButton.tonalIcon(
          onPressed: _opening ? null : _openExternally,
          icon: _opening
              ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
              : const Icon(Icons.open_in_new),
          label: const Text('Open'),
        ),
      ],
    );
  }

  Widget _bytesBuilder(Widget Function(Uint8List bytes) onData) {
    return FutureBuilder<Uint8List>(
      future: _bytes,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const SizedBox(height: 80, child: Center(child: CircularProgressIndicator()));
        }
        if (snap.hasError || snap.data == null) {
          return Text(
            snap.hasError ? ApiError.fromException(snap.error!).userMessage : 'Could not load preview.',
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          );
        }
        return onData(snap.data!);
      },
    );
  }
}
