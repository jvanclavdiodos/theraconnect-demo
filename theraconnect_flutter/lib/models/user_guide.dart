class UserGuideSection {
  final String title;
  final String description;
  final String action;

  const UserGuideSection(
      {required this.title, required this.description, required this.action});

  factory UserGuideSection.fromJson(Map<String, dynamic> json) =>
      UserGuideSection(
        title: json['title'] as String,
        description: json['description'] as String,
        action: json['action'] as String,
      );
}
