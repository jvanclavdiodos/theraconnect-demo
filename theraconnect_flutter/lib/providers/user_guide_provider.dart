import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../config/api_config.dart';
import '../models/user_guide.dart';
import 'auth_provider.dart';

final userGuideProvider = FutureProvider<List<UserGuideSection>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.get(ApiConfig.userGuideEndpoint);
  final sections = response.data['data']['sections'] as List<dynamic>;
  return sections
      .map((item) => UserGuideSection.fromJson(item as Map<String, dynamic>))
      .toList();
});
