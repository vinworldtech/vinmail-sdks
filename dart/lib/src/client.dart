import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;

import 'errors.dart';
import 'models.dart';

/// Default production VinMail API base URL.
const String defaultBaseUrl = 'https://vinmail-api.vinworlddev.com';

/// Current Dart SDK version string.
const String sdkVersion = '0.1.0';

/// Resource wrapper for `/v1/emails` endpoints.
class EmailsResource {
  final VinMail _client;

  const EmailsResource(this._client);

  /// Queues a transactional email for immediate delivery (`POST /v1/emails`).
  Future<SendEmailResponse> send(
    SendEmailRequest request, {
    String? idempotencyKey,
  }) async {
    final key = (idempotencyKey ?? request.idempotencyKey)?.trim();
    final headers = <String, String>{};
    if (key != null && key.isNotEmpty) {
      headers['Idempotency-Key'] = key;
    }

    final json = await _client.request(
      'POST',
      '/v1/emails',
      body: request.toJson(),
      extraHeaders: headers,
    );
    return SendEmailResponse.fromJson(json);
  }

  /// Fetches delivery status and event timeline (`GET /v1/emails/:id`).
  Future<EmailDetails> get(String id) async {
    final cleanId = id.trim();
    if (cleanId.isEmpty) {
      throw const VinMailException(
        'Email id is required.',
        status: 422,
        code: 'validation_error',
      );
    }

    final json = await _client.request(
      'GET',
      '/v1/emails/${Uri.encodeComponent(cleanId)}',
    );
    return EmailDetails.fromJson(json);
  }
}

/// Official VinMail transactional email API client for server-side Dart.
///
/// **Security note:** Never bundle a `vm_live_...` API key inside a client-side
/// Flutter mobile or web app. Call VinMail from a secure backend service
/// (Dart Frog, Serverpod, Shelf, Node.js, Laravel, Python, etc.).
class VinMail {
  final String _apiKey;
  final String _baseUrl;
  final Duration _timeout;
  final int _maxRetries;
  final http.Client _http;
  final bool _ownsHttpClient;

  /// Access transactional email methods (`send`, `get`).
  late final EmailsResource emails;

  /// Creates a new [VinMail] API client.
  VinMail({
    required String apiKey,
    String baseUrl = defaultBaseUrl,
    Duration timeout = const Duration(seconds: 15),
    int maxRetries = 2,
    http.Client? httpClient,
  })  : _apiKey = apiKey.trim(),
        _baseUrl = baseUrl.replaceAll(RegExp(r'/+$'), ''),
        _timeout = timeout,
        _maxRetries = max(0, maxRetries),
        _http = httpClient ?? http.Client(),
        _ownsHttpClient = httpClient == null {
    if (_apiKey.isEmpty) {
      throw const VinMailException(
        'A valid VinMail API key (vm_live_...) is required.',
        status: 401,
        code: 'missing_api_key',
      );
    }
    emails = EmailsResource(this);
  }

  /// Closes the underlying HTTP client when created internally.
  void close() {
    if (_ownsHttpClient) {
      _http.close();
    }
  }

  /// Executes an authenticated JSON request against the VinMail API.
  Future<Map<String, dynamic>> request(
    String method,
    String path, {
    Map<String, Object?>? body,
    Map<String, String>? extraHeaders,
  }) async {
    final uri = Uri.parse('$_baseUrl$path');
    final headers = <String, String>{
      'Authorization': 'Bearer $_apiKey',
      'Accept': 'application/json',
      'User-Agent': 'vinmail-dart/$sdkVersion',
      if (extraHeaders != null) ...extraHeaders,
    };

    String? encodedBody;
    if (body != null) {
      headers['Content-Type'] = 'application/json';
      encodedBody = jsonEncode(body);
    }

    var attempt = 0;
    while (true) {
      try {
        late final http.Response response;
        if (method == 'POST') {
          response = await _http
              .post(uri, headers: headers, body: encodedBody)
              .timeout(_timeout);
        } else {
          response = await _http.get(uri, headers: headers).timeout(_timeout);
        }

        Map<String, dynamic>? decoded;
        if (response.body.isNotEmpty) {
          try {
            final parsed = jsonDecode(response.body);
            if (parsed is Map<String, dynamic>) {
              decoded = parsed;
            }
          } catch (_) {
            decoded = null;
          }
        }

        if (response.statusCode >= 200 && response.statusCode < 300) {
          return decoded ?? const {};
        }

        final retryAfterHeader = response.headers['retry-after'];
        final retryAfter =
            retryAfterHeader != null ? int.tryParse(retryAfterHeader) : null;

        final shouldRetry = attempt < _maxRetries &&
            (response.statusCode == 429 || response.statusCode >= 500);
        if (shouldRetry) {
          attempt += 1;
          final delayMs = retryAfter != null
              ? min(retryAfter * 1000, 5000)
              : min(300 * (1 << (attempt - 1)), 3000);
          await Future<void>.delayed(Duration(milliseconds: delayMs));
          continue;
        }

        final errObj = decoded?['error'];
        final code = (errObj is Map && errObj['code'] is String)
            ? errObj['code'] as String
            : 'http_${response.statusCode}';
        final message = (errObj is Map && errObj['message'] is String)
            ? errObj['message'] as String
            : (response.body.isNotEmpty
                ? response.body.substring(0, min(300, response.body.length))
                : 'VinMail request failed with HTTP ${response.statusCode}.');

        throw VinMailException(
          message,
          status: response.statusCode,
          code: code,
          retryAfter: retryAfter,
        );
      } on VinMailException {
        rethrow;
      } on TimeoutException {
        if (attempt < _maxRetries) {
          attempt += 1;
          await Future<void>.delayed(
            Duration(milliseconds: min(300 * (1 << (attempt - 1)), 3000)),
          );
          continue;
        }
        throw VinMailException(
          'VinMail request timed out after ${_timeout.inSeconds}s.',
          status: 0,
          code: 'timeout',
        );
      } catch (err) {
        if (attempt < _maxRetries) {
          attempt += 1;
          await Future<void>.delayed(
            Duration(milliseconds: min(300 * (1 << (attempt - 1)), 3000)),
          );
          continue;
        }
        throw VinMailException(
          'Network error contacting VinMail: $err',
          status: 0,
          code: 'network_error',
        );
      }
    }
  }
}
