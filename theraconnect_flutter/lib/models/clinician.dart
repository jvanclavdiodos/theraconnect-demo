class Clinician {
  final int id;
  final String name;
  final String? specialization;

  const Clinician({
    required this.id,
    required this.name,
    this.specialization,
  });

  factory Clinician.fromJson(Map<String, dynamic> json) {
    return Clinician(
      id: json['id'] as int,
      name: (json['name'] as String?) ?? 'Clinician',
      specialization: json['specialization'] as String?,
    );
  }
}
