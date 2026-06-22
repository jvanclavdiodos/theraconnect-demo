import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/patient.dart';
import '../../providers/profile_provider.dart';

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _dateOfBirthController = TextEditingController();
  final _contactController = TextEditingController();
  final _addressController = TextEditingController();
  final _emergencyContactController = TextEditingController();
  final _personalIssuesController = TextEditingController();
  String? _gender;
  String? _education;
  String? _employment;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final profile = ref.read(profileProvider);
    profile.whenData((p) {
      if (p != null) {
        _dateOfBirthController.text = p.dateOfBirth ?? '';
        _contactController.text = p.contactNo ?? '';
        _addressController.text = p.address ?? '';
        _emergencyContactController.text = p.emergencyContact ?? '';
        _personalIssuesController.text = p.personalIssues ?? '';
        _gender = Patient.genders.contains(p.gender) ? p.gender : null;
        _education = Patient.educationLevels.contains(p.educationalAttainment) ? p.educationalAttainment : null;
        _employment = Patient.employmentStatuses.contains(p.employmentStatus) ? p.employmentStatus : null;
      }
    });
  }

  @override
  void dispose() {
    _dateOfBirthController.dispose();
    _contactController.dispose();
    _addressController.dispose();
    _emergencyContactController.dispose();
    _personalIssuesController.dispose();
    super.dispose();
  }

  String? _trimOrNull(TextEditingController c) =>
      c.text.trim().isEmpty ? null : c.text.trim();

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _saving = true);

    final error = await ref.read(profileProvider.notifier).updateProfile(
          dateOfBirth: _trimOrNull(_dateOfBirthController),
          gender: _gender,
          educationalAttainment: _education,
          employmentStatus: _employment,
          personalIssues: _trimOrNull(_personalIssuesController),
          contactNo: _trimOrNull(_contactController),
          address: _trimOrNull(_addressController),
          emergencyContact: _trimOrNull(_emergencyContactController),
        );

    if (mounted) {
      setState(() => _saving = false);
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error), backgroundColor: Colors.red),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile updated!'), backgroundColor: Colors.green),
        );
        context.pop();
      }
    }
  }

  DropdownMenuItem<String> _item(String v) =>
      DropdownMenuItem(value: v, child: Text(v));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Edit Profile')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _dateOfBirthController,
              decoration: const InputDecoration(
                labelText: 'Date of Birth',
                hintText: 'YYYY-MM-DD',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.cake),
              ),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _gender,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Gender',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.wc),
              ),
              items: Patient.genders.map(_item).toList(),
              onChanged: (v) => setState(() => _gender = v),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _education,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Educational Attainment',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.school),
              ),
              items: Patient.educationLevels.map(_item).toList(),
              onChanged: (v) => setState(() => _education = v),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _employment,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Employment Status',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.work_outline),
              ),
              items: Patient.employmentStatuses.map(_item).toList(),
              onChanged: (v) => setState(() => _employment = v),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _personalIssuesController,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Personal Issues',
                hintText: 'Anything you want your clinician to know',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.favorite_border),
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _contactController,
              decoration: const InputDecoration(
                labelText: 'Contact Number',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.phone),
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _addressController,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Address',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.home),
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _emergencyContactController,
              decoration: const InputDecoration(
                labelText: 'Emergency Contact',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.emergency),
              ),
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _saving ? null : _save,
              style: FilledButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
              child: _saving
                  ? const CircularProgressIndicator(strokeWidth: 2, color: Colors.white)
                  : const Text('Save Changes'),
            ),
          ],
        ),
      ),
    );
  }
}
