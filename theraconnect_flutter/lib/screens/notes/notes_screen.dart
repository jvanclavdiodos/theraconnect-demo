import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/api_response.dart';
import '../../providers/note_provider.dart';

/// Read-only list of notes the clinician has shared with the patient
/// (e.g. prescriptions, general guidance).
class NotesScreen extends ConsumerWidget {
  const NotesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notesAsync = ref.watch(notesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Notes from your clinician')),
      body: notesAsync.when(
        data: (notes) {
          if (notes.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('Your clinician has not shared any notes yet.',
                    textAlign: TextAlign.center),
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(notesProvider.future),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: notes.length,
              itemBuilder: (context, i) {
                final note = notes[i];
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (note.title != null && note.title!.isNotEmpty)
                          Text(note.title!, style: Theme.of(context).textTheme.titleMedium),
                        if (note.title != null && note.title!.isNotEmpty)
                          const SizedBox(height: 4),
                        Text(note.body),
                        const SizedBox(height: 8),
                        Text(
                          [
                            if (note.clinicianName != null) note.clinicianName!,
                            if (note.createdAt != null) note.createdAt!.split('T').first,
                          ].join(' · '),
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: Theme.of(context).colorScheme.outline),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(ApiError.fromException(e).userMessage)),
      ),
    );
  }
}
