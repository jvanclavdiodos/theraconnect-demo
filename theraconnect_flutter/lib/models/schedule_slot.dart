class ScheduleSlot {
  final String slot;
  final int clinicianId;
  final String clinicianName;
  final bool available;

  const ScheduleSlot({
    required this.slot,
    required this.clinicianId,
    required this.clinicianName,
    required this.available,
  });

  factory ScheduleSlot.fromJson(Map<String, dynamic> json) {
    return ScheduleSlot(
      slot: json['slot'] as String,
      clinicianId: json['clinician_id'] as int,
      clinicianName: json['clinician_name'] as String,
      available: json['available'] as bool,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'slot': slot,
      'clinician_id': clinicianId,
      'clinician_name': clinicianName,
      'available': available,
    };
  }
}
