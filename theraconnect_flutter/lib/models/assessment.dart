/// A standardized questionnaire (PHQ-9 / GAD-7) the clinician assigned to the
/// patient. Summary shape returned by the list endpoint.
class Assessment {
  final int id;
  final String instrument; // 'phq9' | 'gad7'
  final String title; // e.g. "PHQ-9"
  final String? name; // e.g. "Patient Health Questionnaire (depression)"
  final String status; // 'pending' | 'completed'
  final int? score;
  final int? max;
  final String? severity;
  final String? completedAt;
  final String? createdAt;

  const Assessment({
    required this.id,
    required this.instrument,
    required this.title,
    this.name,
    required this.status,
    this.score,
    this.max,
    this.severity,
    this.completedAt,
    this.createdAt,
  });

  bool get isPending => status == 'pending';

  factory Assessment.fromJson(Map<String, dynamic> json) {
    return Assessment(
      id: json['id'] as int,
      instrument: json['instrument'] as String,
      title: json['title'] as String,
      name: json['name'] as String?,
      status: json['status'] as String,
      score: json['score'] as int?,
      max: json['max'] as int?,
      severity: json['severity'] as String?,
      completedAt: json['completed_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

/// Full questionnaire content (items + answer options) returned by the detail
/// endpoint, used to render the fillable form.
class AssessmentDetail {
  final Assessment assessment;
  final String prompt;
  final List<String> options; // shared 0–3 frequency options
  final List<String> items; // per-item prompts
  final List<int>? responses; // prior responses if already completed

  const AssessmentDetail({
    required this.assessment,
    required this.prompt,
    required this.options,
    required this.items,
    this.responses,
  });

  factory AssessmentDetail.fromJson(Map<String, dynamic> json) {
    return AssessmentDetail(
      assessment: Assessment.fromJson(json),
      prompt: json['prompt'] as String? ?? '',
      options: (json['options'] as List<dynamic>? ?? [])
          .map((e) => e.toString())
          .toList(),
      items: (json['items'] as List<dynamic>? ?? [])
          .map((e) => e.toString())
          .toList(),
      responses: (json['responses'] as List<dynamic>?)
          ?.map((e) => (e as num).toInt())
          .toList(),
    );
  }
}
