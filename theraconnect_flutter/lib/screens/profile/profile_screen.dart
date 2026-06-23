import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import 'profile_avatar.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final profile = ref.watch(profileProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  ProfileAvatar(hasAvatar: profile.valueOrNull?.hasAvatar ?? false),
                  const SizedBox(height: 12),
                  Text(authState.user?.name ?? '', style: Theme.of(context).textTheme.titleLarge),
                  Text(authState.user?.email ?? '', style: Theme.of(context).textTheme.bodyMedium),
                  const SizedBox(height: 4),
                  Chip(label: Text('Patient', style: const TextStyle(fontSize: 12))),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Column(
              children: [
                const Padding(
                  padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text('Account', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
                ListTile(
                  leading: const Icon(Icons.edit),
                  title: const Text('Edit Profile'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/profile/edit'),
                ),
                ListTile(
                  leading: const Icon(Icons.notifications),
                  title: const Text('Notifications'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/notifications'),
                ),
                ListTile(
                  leading: const Icon(Icons.description_outlined),
                  title: const Text('Notes from your clinician'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/notes'),
                ),
                ListTile(
                  leading: const Icon(Icons.insights_outlined),
                  title: const Text('My progress'),
                  subtitle: const Text('Mood check-ins & questionnaires'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/progress'),
                ),
                ListTile(
                  leading: const Icon(Icons.download),
                  title: const Text('Downloads'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/downloads'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          profile.when(
            data: (patient) {
              if (patient == null) return const SizedBox.shrink();
              return Card(
                child: Column(
                  children: [
                    const Padding(
                      padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text('Personal Info', style: TextStyle(fontWeight: FontWeight.bold)),
                      ),
                    ),
                    if (patient.dateOfBirth != null)
                      ListTile(
                        leading: const Icon(Icons.cake),
                        title: const Text('Date of Birth'),
                        subtitle: Text(patient.dateOfBirth!),
                      ),
                    if (patient.gender != null)
                      ListTile(
                        leading: const Icon(Icons.wc),
                        title: const Text('Gender'),
                        subtitle: Text(patient.gender!),
                      ),
                    if (patient.educationalAttainment != null)
                      ListTile(
                        leading: const Icon(Icons.school),
                        title: const Text('Educational Attainment'),
                        subtitle: Text(patient.educationalAttainment!),
                      ),
                    if (patient.employmentStatus != null)
                      ListTile(
                        leading: const Icon(Icons.work_outline),
                        title: const Text('Employment Status'),
                        subtitle: Text(patient.employmentStatus!),
                      ),
                    if (patient.personalIssues != null && patient.personalIssues!.isNotEmpty)
                      ListTile(
                        leading: const Icon(Icons.favorite_border),
                        title: const Text('Personal Issues'),
                        subtitle: Text(patient.personalIssues!),
                      ),
                    if (patient.contactNo != null)
                      ListTile(
                        leading: const Icon(Icons.phone),
                        title: const Text('Contact'),
                        subtitle: Text(patient.contactNo!),
                      ),
                    if (patient.address != null)
                      ListTile(
                        leading: const Icon(Icons.home),
                        title: const Text('Address'),
                        subtitle: Text(patient.address!),
                      ),
                    if (patient.emergencyContact != null)
                      ListTile(
                        leading: const Icon(Icons.emergency),
                        title: const Text('Emergency Contact'),
                        subtitle: Text(patient.emergencyContact!),
                      ),
                  ],
                ),
              );
            },
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
          const SizedBox(height: 24),
          OutlinedButton.icon(
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) {
                context.go('/login');
              }
            },
            icon: const Icon(Icons.logout, color: Colors.red),
            label: const Text('Sign Out', style: TextStyle(color: Colors.red)),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size(double.infinity, 48),
              foregroundColor: Colors.red,
              side: const BorderSide(color: Colors.red),
            ),
          ),
        ],
      ),
    );
  }
}
