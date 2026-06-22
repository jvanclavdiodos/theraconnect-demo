class Patient {
  final int id;
  final int userId;
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
      id: json['id'] as int,
      userId: json['user_id'] as int,
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
      'id': id,
      'user_id': userId,
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
