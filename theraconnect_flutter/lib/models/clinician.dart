class Clinician {
  final String id;
  final String name;
  final String? specialization;

  const Clinician({
    required this.id,
    required this.name,
    this.specialization,
  });

  factory Clinician.fromJson(Map<String, dynamic> json) {
    return Clinician(
      id: json['public_id'] as String,
      name: (json['name'] as String?) ?? 'Clinician',
      specialization: json['specialization'] as String?,
    );
  }
}
