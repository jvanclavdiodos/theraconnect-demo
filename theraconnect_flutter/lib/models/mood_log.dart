/// A quick self-reported mood check-in (1–10) the patient logs in the app.
class MoodLog {
  final int id;
  final int score;
  final String? note;
  final String? createdAt;

  const MoodLog({
    required this.id,
    required this.score,
    this.note,
    this.createdAt,
  });

  factory MoodLog.fromJson(Map<String, dynamic> json) {
    return MoodLog(
      id: json['id'] as int,
      score: (json['score'] as num).toInt(),
      note: json['note'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
