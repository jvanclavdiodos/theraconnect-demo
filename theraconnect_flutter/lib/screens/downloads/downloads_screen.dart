import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import '../../models/downloaded_file.dart';
import '../../providers/download_provider.dart';

class DownloadsScreen extends ConsumerWidget {
  const DownloadsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final downloads = ref.watch(downloadsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Downloads')),
      body: downloads.isEmpty
          ? _emptyState(context)
          : Column(
              children: [
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                      horizontal: 16, vertical: 10),
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                  child: Row(
                    children: [
                      const Icon(Icons.folder_outlined, size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Saved in your phone\'s Downloads/TheraConnect folder',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: ListView.separated(
                    padding: const EdgeInsets.all(8),
                    itemCount: downloads.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, i) =>
                        _DownloadTile(file: downloads[i]),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _emptyState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.download_done_outlined,
                size: 64, color: Theme.of(context).disabledColor),
            const SizedBox(height: 16),
            Text(
              'No downloads yet',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(
              'Open an assignment worksheet and tap Download to save it here.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}

class _DownloadTile extends ConsumerWidget {
  final DownloadedFile file;

  const _DownloadTile({required this.file});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Dismissible(
      key: ValueKey(file.fileName),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        color: Colors.red,
        child: const Icon(Icons.delete, color: Colors.white),
      ),
      onDismissed: (_) {
        ref.read(downloadsProvider.notifier).remove(file);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Removed ${file.fileName}')),
        );
      },
      child: ListTile(
        leading: Icon(_iconFor(file.mimeType)),
        title: Text(file.title, maxLines: 1, overflow: TextOverflow.ellipsis),
        subtitle: Text(
          '${file.fileName} · ${_formatSize(file.sizeBytes)} · ${_formatDate(file.savedAtDate)}',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: const Icon(Icons.open_in_new, size: 18),
        onTap: () => _open(context, ref),
      ),
    );
  }

  Future<void> _open(BuildContext context, WidgetRef ref) async {
    final service = ref.read(downloadServiceProvider);
    if (!await service.exists(file)) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('File is no longer available — download it again.'),
            backgroundColor: Colors.orange,
          ),
        );
      }
      return;
    }
    final result = await OpenFilex.open(file.localPath);
    if (result.type != ResultType.done && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Could not open it: ${result.message}'),
          backgroundColor: Colors.orange,
        ),
      );
    }
  }

  IconData _iconFor(String? mime) {
    if (mime == null) return Icons.insert_drive_file_outlined;
    if (mime.contains('pdf')) return Icons.picture_as_pdf_outlined;
    if (mime.contains('word') || mime.contains('msword')) {
      return Icons.description_outlined;
    }
    if (mime.contains('sheet') || mime.contains('excel')) {
      return Icons.table_chart_outlined;
    }
    if (mime.startsWith('image/')) return Icons.image_outlined;
    return Icons.insert_drive_file_outlined;
  }

  String _formatSize(int bytes) {
    if (bytes <= 0) return '—';
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(0)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  String _formatDate(DateTime? date) {
    if (date == null) return '';
    // `savedAt` is captured client-side via `DateTime.now().toIso8601String()`
    // — already local, no conversion needed. Don't `toLocal()` here: doing so
    // on a server timestamp would silently shift the displayed time by the
    // device's UTC offset (the app intentionally stores/renders clinic wall-
    // clock time, not UTC — see lib/utils/date_format.dart).
    final d = date;
    final two = (int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${two(d.month)}-${two(d.day)} ${two(d.hour)}:${two(d.minute)}';
  }
}
