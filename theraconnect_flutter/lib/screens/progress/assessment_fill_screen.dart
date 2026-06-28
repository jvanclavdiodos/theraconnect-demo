import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../models/assessment.dart';
import '../../providers/assessment_provider.dart';
import 'assessments_screen.dart' show severityColor;

/// Renders a PHQ-9 / GAD-7 questionnaire as radio groups (0–3 per item),
/// submits the responses, and shows the resulting score + severity.
class AssessmentFillScreen extends ConsumerStatefulWidget {
  final int assessmentId;

  const AssessmentFillScreen({super.key, required this.assessmentId});

  @override
  ConsumerState<AssessmentFillScreen> createState() =>
      _AssessmentFillScreenState();
}

class _AssessmentFillScreenState extends ConsumerState<AssessmentFillScreen> {
  final Map<int, int> _answers = {}; // item index -> chosen value (0–3)
  bool _submitting = false;

  Future<void> _submit(AssessmentDetail detail) async {
    final colorScheme = Theme.of(context).colorScheme;

    if (_answers.length != detail.items.length) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Please answer every question.'),
          backgroundColor: colorScheme.error,
        ),
      );
      return;
    }

    setState(() => _submitting = true);

    final responses = List<int>.generate(
        detail.items.length, (i) => _answers[i] ?? 0);

    try {
      final result =
          await ref.read(assessmentApiProvider).submit(widget.assessmentId, responses);
      ref.invalidate(assessmentsProvider);
      if (mounted) {
        setState(() => _submitting = false);
        await _showResult(result);
        if (mounted) context.pop();
      }
    } catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(ApiError.fromException(e).userMessage),
            backgroundColor: colorScheme.error,
          ),
        );
      }
    }
  }

  Future<void> _showResult(Assessment result) {
    return showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Thank you'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('${result.title} score',
                style: Theme.of(ctx).textTheme.bodyMedium),
            const SizedBox(height: 8),
            Text(
              '${result.score ?? '—'}${result.max != null ? ' / ${result.max}' : ''}',
              style: Theme.of(ctx).textTheme.headlineMedium?.copyWith(
                  color: severityColor(result.severity),
                  fontWeight: FontWeight.bold),
            ),
            if (result.severity != null) ...[
              const SizedBox(height: 4),
              Chip(
                label: Text(result.severity!),
                backgroundColor:
                    severityColor(result.severity).withValues(alpha: 0.15),
              ),
            ],
            const SizedBox(height: 12),
            Text(
              'Your clinician will see this and discuss it with you.',
              textAlign: TextAlign.center,
              style: Theme.of(ctx).textTheme.bodySmall,
            ),
          ],
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Done'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(assessmentDetailProvider(widget.assessmentId));

    return Scaffold(
      appBar: AppBar(title: const Text('Questionnaire')),
      body: detailAsync.when(
        data: (detail) {
          // Already completed → don't allow re-filling; show the score instead.
          if (detail.assessment.status == 'completed') {
            return _CompletedView(assessment: detail.assessment);
          }
          return _buildForm(context, detail);
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) =>
            Center(child: Text(ApiError.fromException(e).userMessage)),
      ),
    );
  }

  Widget _buildForm(BuildContext context, AssessmentDetail detail) {
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(detail.assessment.title,
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 4),
                      Text(detail.prompt,
                          style: Theme.of(context).textTheme.bodyMedium),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 8),
              ...List.generate(detail.items.length, (i) {
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${i + 1}. ${detail.items[i]}',
                            style: Theme.of(context).textTheme.titleSmall),
                        const SizedBox(height: 4),
                        RadioGroup<int>(
                          groupValue: _answers[i],
                          onChanged: (val) =>
                              setState(() => _answers[i] = val!),
                          child: Column(
                            children: List.generate(detail.options.length, (v) {
                              return RadioListTile<int>(
                                dense: true,
                                contentPadding: EdgeInsets.zero,
                                title: Text(detail.options[v]),
                                value: v,
                              );
                            }),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }),
            ],
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton(
              onPressed: _submitting ? null : () => _submit(detail),
              style: FilledButton.styleFrom(
                  minimumSize: const Size(double.infinity, 48)),
              child: _submitting
                  ? CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Theme.of(context).colorScheme.onPrimary,
                    )
                  : Text('Submit (${_answers.length}/${detail.items.length})'),
            ),
          ),
        ),
      ],
    );
  }
}

class _CompletedView extends StatelessWidget {
  final Assessment assessment;
  const _CompletedView({required this.assessment});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.check_circle, size: 48, color: severityColor(assessment.severity)),
            const SizedBox(height: 12),
            Text('${assessment.title} already completed',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              '${assessment.score ?? '—'}${assessment.max != null ? ' / ${assessment.max}' : ''}'
              '${assessment.severity != null ? ' · ${assessment.severity}' : ''}',
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ],
        ),
      ),
    );
  }
}
