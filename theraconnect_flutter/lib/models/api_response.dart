class ApiError implements Exception {
  final String message;
  final Map<String, List<String>>? errors;
  final int statusCode;

  const ApiError({
    required this.message,
    this.errors,
    this.statusCode = 0,
  });

  factory ApiError.fromJson(Map<String, dynamic> json, {int statusCode = 0}) {
    Map<String, List<String>>? parsedErrors;
    if (json['errors'] is Map) {
      parsedErrors = (json['errors'] as Map<String, dynamic>).map(
        (key, value) => MapEntry(
          key,
          (value as List<dynamic>).map((e) => e.toString()).toList(),
        ),
      );
    }
    return ApiError(
      message: json['message'] as String? ?? 'An error occurred',
      errors: parsedErrors,
      statusCode: statusCode,
    );
  }

  String get userMessage {
    if (errors != null && errors!.isNotEmpty) {
      return errors!.values.first.first;
    }
    return message;
  }
}
