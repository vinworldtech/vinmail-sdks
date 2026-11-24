import 'attachments.dart';

/// Request payload for `POST /v1/emails`.
class SendEmailRequest {
  /// Verified sender address (e.g. `Acme <billing@acme.com>`). Optional if the project has a defaultFrom.
  final String? from;

  /// Recipient email addresses (1 to 50 addresses without display names).
  final List<String> to;

  /// Optional Reply-To address (`email` or `Name <email>`).
  final String? replyTo;

  /// Single-line email subject (required unless using a template that defines one).
  final String? subject;

  /// HTML body (up to 500 KB).
  final String? html;

  /// Plain-text body (up to 500 KB).
  final String? text;

  /// Template name (project template or built-in global template: `otp`, `notification`, `notification-action`).
  final String? template;

  /// Template variables (never stored or logged by VinMail).
  final Map<String, Object?>? variables;

  /// Up to 5 key/value tags attached to delivery events.
  final Map<String, String>? tags;

  /// Optional business file attachments (max 5 files, 5 MB each, 7.5 MB combined).
  final List<VinMailAttachment>? attachments;

  /// Optional idempotency key sent in the `Idempotency-Key` header.
  final String? idempotencyKey;

  const SendEmailRequest({
    required this.to,
    this.from,
    this.replyTo,
    this.subject,
    this.html,
    this.text,
    this.template,
    this.variables,
    this.tags,
    this.attachments,
    this.idempotencyKey,
  });

  /// Serializes this request into the JSON body expected by VinMail.
  Map<String, Object?> toJson() {
    final map = <String, Object?>{
      'to': to.length == 1 ? to.first : to,
    };
    if (from != null) map['from'] = from;
    if (replyTo != null) map['replyTo'] = replyTo;
    if (subject != null) map['subject'] = subject;
    if (html != null) map['html'] = html;
    if (text != null) map['text'] = text;
    if (template != null) map['template'] = template;
    if (variables != null) map['variables'] = variables;
    if (tags != null) map['tags'] = tags;
    if (attachments != null && attachments!.isNotEmpty) {
      map['attachments'] = prepareAttachments(attachments!);
    }
    return map;
  }
}

/// Response returned by `POST /v1/emails` (`202 Accepted`).
class SendEmailResponse {
  final String id;
  final String status;
  final List<String> suppressed;

  const SendEmailResponse({
    required this.id,
    required this.status,
    this.suppressed = const [],
  });

  factory SendEmailResponse.fromJson(Map<String, dynamic> json) {
    final rawSuppressed = json['suppressed'];
    return SendEmailResponse(
      id: (json['id'] as String?) ?? '',
      status: (json['status'] as String?) ?? 'queued',
      suppressed: rawSuppressed is List
          ? rawSuppressed.map((e) => e.toString()).toList()
          : const [],
    );
  }
}

/// Delivery event recorded for an email.
class EmailEvent {
  final String type;
  final String? recipient;
  final Map<String, dynamic>? detail;
  final String createdAt;

  const EmailEvent({
    required this.type,
    required this.createdAt,
    this.recipient,
    this.detail,
  });

  factory EmailEvent.fromJson(Map<String, dynamic> json) {
    return EmailEvent(
      type: (json['type'] as String?) ?? '',
      recipient: json['recipient'] as String?,
      detail: json['detail'] is Map<String, dynamic>
          ? json['detail'] as Map<String, dynamic>
          : null,
      createdAt: (json['createdAt'] as String?) ?? '',
    );
  }
}

/// Full delivery status and event timeline returned by `GET /v1/emails/:id`.
class EmailDetails {
  final String id;
  final String status;
  final List<String> to;
  final String subject;
  final String? templateName;
  final int recipientCount;
  final String? error;
  final String createdAt;
  final String? sentAt;
  final List<EmailEvent> events;

  const EmailDetails({
    required this.id,
    required this.status,
    required this.to,
    required this.subject,
    required this.recipientCount,
    required this.createdAt,
    required this.events,
    this.templateName,
    this.error,
    this.sentAt,
  });

  factory EmailDetails.fromJson(Map<String, dynamic> json) {
    final rawTo = json['to'];
    final rawEvents = json['events'];
    return EmailDetails(
      id: (json['id'] as String?) ?? '',
      status: (json['status'] as String?) ?? '',
      to: rawTo is List ? rawTo.map((e) => e.toString()).toList() : const [],
      subject: (json['subject'] as String?) ?? '',
      templateName: json['templateName'] as String?,
      recipientCount: (json['recipientCount'] as num?)?.toInt() ?? 0,
      error: json['error'] as String?,
      createdAt: (json['createdAt'] as String?) ?? '',
      sentAt: json['sentAt'] as String?,
      events: rawEvents is List
          ? rawEvents
              .whereType<Map<String, dynamic>>()
              .map(EmailEvent.fromJson)
              .toList()
          : const [],
    );
  }
}
