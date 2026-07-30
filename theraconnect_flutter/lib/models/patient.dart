class Patient {
  final String id;
  final String userId;
  final bool hasAvatar;
  final String? assignedClinicianId;
  final List<String> assignedClinicianIds;
  final String? requestedClinicianId;
  final String? clinicianRequestStatus;
  final String? dateOfBirth;
  final String? gender;
  final String? educationalAttainment;
  final String? employmentStatus;
  final String? personalIssues;
  final String? contactNo;
  final String? address;
  final String? emergencyContact;
  final String? notes;
  final String? createdAt;
  final String? updatedAt;

  const Patient({
    required this.id,
    required this.userId,
    this.hasAvatar = false,
    this.assignedClinicianId,
    this.assignedClinicianIds = const [],
    this.requestedClinicianId,
    this.clinicianRequestStatus,
    this.dateOfBirth,
    this.gender,
    this.educationalAttainment,
    this.employmentStatus,
    this.personalIssues,
    this.contactNo,
    this.address,
    this.emergencyContact,
    this.notes,
    this.createdAt,
    this.updatedAt,
  });

  factory Patient.fromJson(Map<String, dynamic> json) {
    return Patient(
      id: json['public_id'] as String,
      userId: json['user_public_id'] as String,
      hasAvatar: json['has_avatar'] as bool? ?? false,
      assignedClinicianId: json['assigned_clinician_public_id'] as String?,
      assignedClinicianIds:
          (json['assigned_clinician_public_ids'] as List<dynamic>?)
              ?.map((id) => id as String)
              .toList() ??
          const [],
      requestedClinicianId:
          json['requested_clinician_public_id'] as String?,
      clinicianRequestStatus: json['clinician_request_status'] as String?,
      dateOfBirth: json['date_of_birth'] as String?,
      gender: json['gender'] as String?,
      educationalAttainment: json['educational_attainment'] as String?,
      employmentStatus: json['employment_status'] as String?,
      personalIssues: json['personal_issues'] as String?,
      contactNo: json['contact_no'] as String?,
      address: json['address'] as String?,
      emergencyContact: json['emergency_contact'] as String?,
      notes: json['notes'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'public_id': id,
      'user_public_id': userId,
      'has_avatar': hasAvatar,
      'assigned_clinician_public_id': assignedClinicianId,
      'assigned_clinician_public_ids': assignedClinicianIds,
      'requested_clinician_public_id': requestedClinicianId,
      'clinician_request_status': clinicianRequestStatus,
      'date_of_birth': dateOfBirth,
      'gender': gender,
      'educational_attainment': educationalAttainment,
      'employment_status': employmentStatus,
      'personal_issues': personalIssues,
      'contact_no': contactNo,
      'address': address,
      'emergency_contact': emergencyContact,
      'notes': notes,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  static const genders = ['Male', 'Female', 'Other', 'Prefer not to say'];
  static const educationLevels = ['None', 'Elementary', 'High School', 'Vocational', 'College', 'Postgraduate'];
  static const employmentStatuses = ['Employed', 'Self-employed', 'Unemployed', 'Student', 'Retired'];
}
