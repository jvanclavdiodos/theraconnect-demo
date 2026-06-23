/// A therapy goal co-defined by the clinician, optionally with the most recent
/// Goal Attainment Scaling (GAS) rating. Read-only on the patient app.
class TherapyGoal {
  final int id;
  final String description;
  final String status; // 'active' | 'met'
  final String? targetDate;
  final GoalRatingSummary? latestRating;

  const TherapyGoal({
    required this.id,
    required this.description,
    required this.status,
    this.targetDate,
    this.latestRating,
  });

  factory TherapyGoal.fromJson(Map<String, dynamic> json) {
    return TherapyGoal(
      id: json['id'] as int,
      description: json['description'] as String,
      status: json['status'] as String,
      targetDate: json['target_date'] as String?,
      latestRating: json['latest_rating'] is Map<String, dynamic>
          ? GoalRatingSummary.fromJson(json['latest_rating'] as Map<String, dynamic>)
          : null,
    );
  }
}

class GoalRatingSummary {
  final int rating; // -2 … +2
  final String? label;
  final String? note;
  final String? createdAt;

  const GoalRatingSummary({
    required this.rating,
    this.label,
    this.note,
    this.createdAt,
  });

  factory GoalRatingSummary.fromJson(Map<String, dynamic> json) {
    return GoalRatingSummary(
      rating: (json['rating'] as num).toInt(),
      label: json['label'] as String?,
      note: json['note'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
