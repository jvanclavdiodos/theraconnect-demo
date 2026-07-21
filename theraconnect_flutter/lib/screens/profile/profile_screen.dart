import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../providers/theme_provider.dart';
import '../../models/patient.dart';
import 'profile_avatar.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final profile = ref.watch(profileProvider);
    final themeMode = ref.watch(themeModeProvider);
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // ── Avatar + name card ────────────────────────────────────────────
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  ProfileAvatar(
                      hasAvatar: profile.valueOrNull?.hasAvatar ?? false),
                  const SizedBox(height: 12),
                  Text(authState.user?.name ?? '',
                      style: Theme.of(context).textTheme.titleLarge),
                  Text(authState.user?.email ?? '',
                      style: Theme.of(context).textTheme.bodyMedium),
                  const SizedBox(height: 4),
                  const Chip(
                      label: Text('Patient', style: TextStyle(fontSize: 12))),
                ],
              ),
            ),
          ),

          // ── Clinician request status banner ───────────────────────────────
          profile.whenOrNull(
                data: (patient) => _ClinicianRequestBanner(
                    patient: patient, colorScheme: colorScheme),
              ) ??
              const SizedBox.shrink(),

          const SizedBox(height: 16),

          // ── Account actions card ──────────────────────────────────────────
          Card(
            child: Column(
              children: [
                const Padding(
                  padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text('Account',
                        style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
                ListTile(
                  leading: const Icon(Icons.edit),
                  title: const Text('Edit Profile'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/profile/edit'),
                ),
                ListTile(
                  leading: const Icon(Icons.lock_outline),
                  title: const Text('Change Password'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/profile/password'),
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
                  leading: const Icon(Icons.help_outline),
                  title: const Text('User Guide'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/guide'),
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

          // ── Appearance card ───────────────────────────────────────────────
          Card(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Appearance',
                      style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  SegmentedButton<ThemeMode>(
                    segments: const [
                      ButtonSegment(
                        value: ThemeMode.light,
                        icon: Icon(Icons.light_mode_outlined),
                        label: Text('Light'),
                      ),
                      ButtonSegment(
                        value: ThemeMode.system,
                        icon: Icon(Icons.brightness_auto_outlined),
                        label: Text('System'),
                      ),
                      ButtonSegment(
                        value: ThemeMode.dark,
                        icon: Icon(Icons.dark_mode_outlined),
                        label: Text('Dark'),
                      ),
                    ],
                    selected: {themeMode},
                    onSelectionChanged: (s) =>
                        ref.read(themeModeProvider.notifier).setMode(s.first),
                    style: ButtonStyle(
                      minimumSize: WidgetStateProperty.all(
                        const Size.fromHeight(42),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // ── Personal info card ────────────────────────────────────────────
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
                        child: Text('Personal Info',
                            style: TextStyle(fontWeight: FontWeight.bold)),
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
                    if (patient.personalIssues != null &&
                        patient.personalIssues!.isNotEmpty)
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

          // ── Sign out ──────────────────────────────────────────────────────
          OutlinedButton.icon(
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) {
                context.go('/login');
              }
            },
            icon: Icon(Icons.logout, color: colorScheme.error),
            label: Text('Sign Out', style: TextStyle(color: colorScheme.error)),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size(double.infinity, 48),
              foregroundColor: colorScheme.error,
              side: BorderSide(color: colorScheme.error),
            ),
          ),
        ],
      ),
    );
  }
}

class _ClinicianRequestBanner extends StatelessWidget {
  final Patient? patient;
  final ColorScheme colorScheme;

  const _ClinicianRequestBanner(
      {required this.patient, required this.colorScheme});

  @override
  Widget build(BuildContext context) {
    if (patient == null) return const SizedBox.shrink();

    final status = patient!.clinicianRequestStatus;
    if (status == null || patient!.assignedClinicianId != null) {
      return const SizedBox.shrink();
    }

    final (icon, color, message) = switch (status) {
      'pending' => (
          Icons.hourglass_top_outlined,
          Colors.amber.shade700,
          'Your clinician request is pending approval.',
        ),
      'denied' => (
          Icons.info_outline,
          colorScheme.error,
          'Your clinician request was not approved. Please contact the clinic.',
        ),
      _ => (
          Icons.info_outline,
          colorScheme.primary,
          'Clinician status: $status'
        ),
    };

    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Card(
        color: color.withValues(alpha: 0.12),
        elevation: 0,
        child: ListTile(
          leading: Icon(icon, color: color),
          title: Text(message, style: TextStyle(color: color, fontSize: 13)),
        ),
      ),
    );
  }
}
