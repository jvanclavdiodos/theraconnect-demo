import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../models/mood_log.dart';
import '../../providers/mood_provider.dart';
import '../../providers/assessment_provider.dart';

/// "My progress" — a quick mood check-in (1–10) with a recent trend, plus a
/// shortcut to the questionnaires. The clinician sees the same data on the web
/// progress page.
class MyProgressScreen extends ConsumerStatefulWidget {
  const MyProgressScreen({super.key});

  @override
  ConsumerState<MyProgressScreen> createState() => _MyProgressScreenState();
}

class _MyProgressScreenState extends ConsumerState<MyProgressScreen> {
  double _score = 5;
  final _noteController = TextEditingController();
  bool _logging = false;

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _logMood() async {
    setState(() => _logging = true);
    try {
      await ref.read(moodApiProvider).logMood(
            _score.round(),
            note: _noteController.text.trim(),
          );
      ref.invalidate(moodLogsProvider);
      if (mounted) {
        _noteController.clear();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Mood logged. Thanks for checking in!'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(ApiError.fromException(e).userMessage),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _logging = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final moodAsync = ref.watch(moodLogsProvider);
    final pendingCount = ref
            .watch(assessmentsProvider)
            .valueOrNull
            ?.where((a) => a.isPending)
            .length ??
        0;

    return Scaffold(
      appBar: AppBar(title: const Text('My progress')),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(moodLogsProvider);
          await ref.read(moodLogsProvider.future);
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // ── Mood check-in ────────────────────────────────────────────
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('How are you feeling right now?',
                        style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Text('1'),
                        Expanded(
                          child: Slider(
                            value: _score,
                            min: 1,
                            max: 10,
                            divisions: 9,
                            label: '${_score.round()}',
                            onChanged: (v) => setState(() => _score = v),
                          ),
                        ),
                        const Text('10'),
                      ],
                    ),
                    Center(
                      child: Text(
                        '${_score.round()} · ${_moodLabel(_score.round())}',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: _moodColor(_score.round()),
                            fontWeight: FontWeight.bold),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _noteController,
                      maxLength: 255,
                      decoration: const InputDecoration(
                        labelText: 'Add a note (optional)',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 8),
                    FilledButton.icon(
                      onPressed: _logging ? null : _logMood,
                      icon: _logging
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.add),
                      label: const Text('Log mood'),
                      style: FilledButton.styleFrom(
                          minimumSize: const Size(double.infinity, 48)),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // ── Questionnaires shortcut ─────────────────────────────────
            Card(
              child: ListTile(
                leading: const Icon(Icons.fact_check_outlined),
                title: const Text('My questionnaires'),
                subtitle: Text(pendingCount > 0
                    ? '$pendingCount to complete'
                    : 'PHQ-9 / GAD-7 history'),
                trailing: pendingCount > 0
                    ? Badge(label: Text('$pendingCount'))
                    : const Icon(Icons.chevron_right),
                onTap: () => context.push('/assessments'),
              ),
            ),
            const SizedBox(height: 16),

            // ── Mood trend ──────────────────────────────────────────────
            Text('Recent mood',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            moodAsync.when(
              data: (logs) {
                if (logs.isEmpty) {
                  return const Card(
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Center(
                          child: Text('No check-ins yet. Log your first above.')),
                    ),
                  );
                }
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: _MoodTrend(logs: logs.reversed.toList()),
                  ),
                );
              },
              loading: () =>
                  const Center(child: Padding(
                      padding: EdgeInsets.all(24),
                      child: CircularProgressIndicator())),
              error: (e, _) =>
                  Center(child: Text(ApiError.fromException(e).userMessage)),
            ),
          ],
        ),
      ),
    );
  }
}

String _moodLabel(int score) {
  if (score <= 2) return 'Very low';
  if (score <= 4) return 'Low';
  if (score <= 6) return 'Okay';
  if (score <= 8) return 'Good';
  return 'Great';
}

Color _moodColor(int score) {
  if (score <= 2) return Colors.red;
  if (score <= 4) return Colors.deepOrange;
  if (score <= 6) return Colors.amber.shade700;
  if (score <= 8) return Colors.lightGreen.shade700;
  return Colors.green;
}

/// A dependency-free bar chart of recent mood scores (oldest → newest).
class _MoodTrend extends StatelessWidget {
  final List<MoodLog> logs;
  const _MoodTrend({required this.logs});

  @override
  Widget build(BuildContext context) {
    final recent = logs.length > 14 ? logs.sublist(logs.length - 14) : logs;
    return SizedBox(
      height: 110,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: recent.map((m) {
          return Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text('${m.score}',
                      style: Theme.of(context).textTheme.labelSmall),
                  const SizedBox(height: 2),
                  Container(
                    height: 8.0 * m.score,
                    decoration: BoxDecoration(
                      color: _moodColor(m.score),
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    (m.createdAt ?? '').split('T').first.split('-').skip(1).join('/'),
                    style: Theme.of(context).textTheme.labelSmall,
                    maxLines: 1,
                    overflow: TextOverflow.clip,
                  ),
                ],
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}
