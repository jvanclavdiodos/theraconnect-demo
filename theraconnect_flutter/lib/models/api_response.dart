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

  /// Construct an ApiError from any thrown object. Used by screens that
  /// receive `AsyncValue.error`'s `e` and by exception-handling blocks.
  ///
  /// Behaviour:
  ///   - If `e` is already an ApiError (the API client parses Dio errors
  ///     into ApiError via handleDioError), pass through unchanged so the
  ///     backend's structured `{message, errors}` shape is preserved.
  ///   - Otherwise, collapse to a generic, patient-friendly message — never
  ///     leak internal stack traces, API paths, or backend exception text
  ///     to end users.
  factory ApiError.fromException(Object e) {
    if (e is ApiError) return e;

    return const ApiError(
      message: 'Something went wrong. Please try again.',
      statusCode: 0,
    );
  }

  String get userMessage {
    if (errors != null && errors!.isNotEmpty) {
      return errors!.values.first.first;
    }
    return message;
  }
}

