class UserGuideSection {
  final String title;
  final String description;
  final String action;
  final String? beforeYouStart;
  final List<String> steps;
  final String expectedResult;
  final List<String> tips;

  const UserGuideSection({
    required this.title,
    required this.description,
    required this.action,
    required this.steps,
    required this.expectedResult,
    this.beforeYouStart,
    this.tips = const [],
  });

  factory UserGuideSection.fromJson(Map<String, dynamic> json) =>
      UserGuideSection(
        title: json['title'] as String,
        description: json['description'] as String,
        action: json['action'] as String,
        beforeYouStart: json['before_you_start'] as String?,
        steps: (json['steps'] as List<dynamic>? ?? const [])
            .map((step) => step as String)
            .toList(),
        expectedResult: json['expected_result'] as String? ?? '',
        tips: (json['tips'] as List<dynamic>? ?? const [])
            .map((tip) => tip as String)
            .toList(),
      );
}
