/// Exception thrown when a VinMail API request or client-side validation fails.
class VinMailException implements Exception {
  /// Human-readable description of the failure.
  final String message;

  /// HTTP status code (`0` for network or timeout failures).
  final int status;

  /// Machine-readable VinMail error code (e.g. `domain_not_verified`).
  final String code;

  /// Optional `Retry-After` delay in seconds (present on HTTP 429 responses).
  final int? retryAfter;

  const VinMailException(
    this.message, {
    this.status = 0,
    this.code = 'vinmail_error',
    this.retryAfter,
  });

  @override
  String toString() => 'VinMailException($status, $code): $message';
}
