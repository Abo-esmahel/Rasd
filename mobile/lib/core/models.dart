class User {
  final int id;
  final String name;
  final String username;
  final String role;
  final String? avatarUrl;
  final String? personalNumber;

  User({
    required this.id,
    required this.name,
    required this.username,
    required this.role,
    this.avatarUrl,
    this.personalNumber,
  });

  bool get isMonitor => role == 'monitor';
  bool get isWriter => role == 'report_writer';
  bool get isGM => role == 'general_manager';

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'username': username,
    'role': role,
    if (avatarUrl != null) 'avatar_url': avatarUrl,
    if (personalNumber != null) 'personal_number': personalNumber,
  };

  factory User.fromJson(Map<String, dynamic> j) => User(
    id: _int(j['id']),
    name: (j['name'] ?? j['localized_name'] ?? j['username'] ?? '?').toString(),
    username: (j['username'] ?? '').toString(),
    role: (j['role'] ?? 'monitor').toString(),
    avatarUrl: j['avatar_url']?.toString(),
    personalNumber: j['personal_number']?.toString(),
  );
}

class Attachment {
  final int id;
  final String name;
  final String mime;
  final int size;

  Attachment({
    required this.id,
    required this.name,
    required this.mime,
    required this.size,
  });

  bool get isImage => mime.startsWith('image/');
  bool get isVideo => mime.startsWith('video/');
  bool get isAudio => mime.startsWith('audio/');

  factory Attachment.fromJson(Map<String, dynamic> j) => Attachment(
    id: _int(j['id']),
    name: (j['original_name'] ?? j['name'] ?? '').toString(),
    mime: (j['mime_type'] ?? j['mime'] ?? '').toString(),
    size: _int(j['file_size'] ?? j['size']),
  );
}

class Note {
  final int id;
  final int userId;
  final int floorNumber;
  final int cameraNumber;
  final DateTime? observedAt;
  final DateTime? observedEndAt;
  final String? observedAtRaw;
  final String? observedEndAtRaw;
  final String description;
  final String status;
  final String? rejectionReason;
  final int? processedBy;
  final User? owner;
  final User? processor;
  final List<Attachment> attachments;
  final DateTime? createdAt;
  final Map<String, int> reactionCounts;
  final String? myReaction;

  final bool canReact;

  Note({
    required this.id,
    required this.userId,
    required this.floorNumber,
    required this.cameraNumber,
    required this.observedAt,
    required this.observedEndAt,
    required this.observedAtRaw,
    required this.observedEndAtRaw,
    required this.description,
    required this.status,
    required this.rejectionReason,
    required this.processedBy,
    required this.owner,
    required this.processor,
    required this.attachments,
    required this.createdAt,
    this.reactionCounts = const {},
    this.myReaction,
    this.canReact = false,
  });

  bool get isDraft => status == 'draft';
  bool get isPending => status == 'pending';
  bool get isAccepted => status == 'accepted';
  bool get isRejected => status == 'rejected';

  factory Note.fromJson(Map<String, dynamic> j) => Note(
    id: _int(j['id']),
    userId: _int(j['user_id']),
    floorNumber: _int(j['floor_number']),
    cameraNumber: _int(j['camera_number']),
    observedAt: _dt(j['observed_at']),
    observedEndAt: _dt(j['observed_end_at']),
    observedAtRaw: j['observed_at']?.toString(),
    observedEndAtRaw: j['observed_end_at']?.toString(),
    description: (j['description'] ?? '').toString(),
    status: (j['status'] ?? '').toString(),
    rejectionReason: j['rejection_reason']?.toString(),
    processedBy: j['processed_by'] == null ? null : _int(j['processed_by']),
    owner: j['owner'] is Map
        ? User.fromJson(Map<String, dynamic>.from(j['owner'] as Map))
        : null,
    processor: j['processor'] is Map
        ? User.fromJson(Map<String, dynamic>.from(j['processor'] as Map))
        : null,
    attachments: (j['attachments'] is List)
        ? (j['attachments'] as List)
              .whereType<Map<String, dynamic>>()
              .map(Attachment.fromJson)
              .toList()
        : const [],
    createdAt: _dt(j['created_at']),
    reactionCounts: _reactionCounts(j['reaction_counts']),
    myReaction: j['my_reaction']?.toString(),
    canReact: j['can_react'] == true,
  );
}

class Report {
  final int id;
  final int authorId;
  final String title;
  final String reportDate;
  final String status;
  final bool locked;
  final bool visibleToMonitors;
  final String? content;
  final String? summary;
  final String? recommendations;
  final User? author;
  final List<Note> notes;
  final int? notesCount;
  final int commentsCount;
  final int reactionsCount;
  final Map<String, int> reactionCounts;
  final String? myReaction;
  final List<EngagementComment> comments;

  final bool canComment;
  final bool canReact;

  final List<ReportTextBlock> summaryBlocks;
  final List<ReportTextBlock> contentBlocks;
  final List<ReportTextBlock> recommendationBlocks;

  Report({
    required this.id,
    required this.authorId,
    required this.title,
    required this.reportDate,
    required this.status,
    required this.locked,
    required this.visibleToMonitors,
    required this.content,
    required this.summary,
    required this.recommendations,
    required this.author,
    required this.notes,
    required this.notesCount,
    this.commentsCount = 0,
    this.reactionsCount = 0,
    this.reactionCounts = const {},
    this.myReaction,
    this.comments = const [],
    this.canComment = false,
    this.canReact = false,
    this.summaryBlocks = const [],
    this.contentBlocks = const [],
    this.recommendationBlocks = const [],
  });

  bool get isPublished => status == 'published';
  bool get isDraft => status == 'draft';

  DateTime? get reportDateTime {
    final t = reportDate.trim();
    if (t.isEmpty) return null;
    return DateTime.tryParse(t.length >= 10 ? t.substring(0, 10) : t);
  }

  factory Report.fromJson(Map<String, dynamic> j) {
    final notesRaw = j['notes'];
    final rd = (j['report_date'] ?? '').toString();
    final tb = j['text_blocks'] is Map
        ? Map<String, dynamic>.from(j['text_blocks'] as Map)
        : <String, dynamic>{};
    final content = j['content']?.toString();
    final summary = j['ai_summary']?.toString() ?? j['summary']?.toString();
    final recommendations =
        j['ai_recommendations']?.toString() ?? j['recommendations']?.toString();
    return Report(
      id: _int(j['id']),
      authorId: _int(j['author_id']),
      title: (j['title'] ?? '').toString(),
      reportDate: rd.length >= 10 ? rd.substring(0, 10) : rd,
      status: (j['status'] ?? 'draft').toString(),
      locked:
          j['is_locked'] == true ||
          j['locked'] == true ||
          j['isLocked'] == true,
      visibleToMonitors:
          j['visible_to_monitors'] == null ||
          j['visible_to_monitors'] == true ||
          j['visible_to_monitors'] == 1 ||
          j['visible_to_monitors']?.toString() == '1' ||
          j['visible_to_monitors']?.toString().toLowerCase() == 'true',
      content: content,
      summary: summary,
      recommendations: recommendations,
      author: j['author'] is Map
          ? User.fromJson(Map<String, dynamic>.from(j['author'] as Map))
          : null,
      notes: (notesRaw is List)
          ? notesRaw
                .whereType<Map<String, dynamic>>()
                .map(Note.fromJson)
                .toList()
          : const [],
      notesCount: j['notes_count'] is int
          ? j['notes_count'] as int
          : (notesRaw is List ? notesRaw.length : null),
      commentsCount: _int(j['comments_count']),
      reactionsCount: _int(j['reactions_count']),
      reactionCounts: _reactionCounts(j['reaction_counts']),
      myReaction: j['my_reaction']?.toString(),
      comments: (j['comments'] is List)
          ? (j['comments'] as List)
                .whereType<Map>()
                .map(
                  (e) =>
                      EngagementComment.fromJson(Map<String, dynamic>.from(e)),
                )
                .toList()
          : const [],
      canComment: j['can_comment'] == true,
      canReact: j['can_react'] == true,
      summaryBlocks: _blocksOrFallback(
        ReportTextBlock.listFrom(tb['summary']),
        summary,
      ),
      contentBlocks: _blocksOrFallback(
        ReportTextBlock.listFrom(tb['content']),
        content,
      ),
      recommendationBlocks: _blocksOrFallback(
        ReportTextBlock.listFrom(tb['recommendations']),
        recommendations,
      ),
    );
  }

  static List<ReportTextBlock> _blocksOrFallback(
    List<ReportTextBlock> blocks,
    String? raw,
  ) {
    if (blocks.isNotEmpty) return blocks;
    final text = (raw ?? '').trim();
    if (text.isEmpty) return const [];
    return text
        .split(RegExp(r'\s*\n\s*'))
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .map((s) => ReportTextBlock(type: 'paragraph', text: s))
        .toList();
  }
}

class ReportTextBlock {
  final String type;
  final String text;
  final int order;

  const ReportTextBlock({
    required this.type,
    required this.text,
    this.order = 0,
  });

  bool get isHeading => type == 'heading';
  bool get isItem => type == 'item';

  static List<ReportTextBlock> listFrom(Object? raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map(
          (e) => ReportTextBlock(
            type: (e['type'] ?? 'paragraph').toString(),
            text: (e['text'] ?? '').toString().trim(),
            order: e['order'] is int ? e['order'] as int : 0,
          ),
        )
        .where((b) => b.text.isNotEmpty)
        .toList();
  }
}

class EngagementComment {
  final int id;
  final String body;
  final String? author;
  final int? authorId;
  final String? avatarUrl;
  final DateTime? createdAt;
  final Map<String, int> reactionCounts;
  final String? myReaction;
  final List<EngagementComment> replies;
  EngagementComment({
    required this.id,
    required this.body,
    this.author,
    this.authorId,
    this.avatarUrl,
    this.createdAt,
    this.reactionCounts = const {},
    this.myReaction,
    this.replies = const [],
  });
  factory EngagementComment.fromJson(Map<String, dynamic> j) {
    final user = j['user'] is Map
        ? Map<String, dynamic>.from(j['user'] as Map)
        : <String, dynamic>{};
    final rawId = user['id'];
    return EngagementComment(
      id: _int(j['id']),
      body: (j['body'] ?? '').toString(),
      author: user['name']?.toString(),
      authorId: rawId is num ? rawId.toInt() : null,
      avatarUrl: user['avatar_url']?.toString(),
      createdAt: _dt(j['created_at']),
      reactionCounts: _reactionCounts(j['reaction_counts']),
      myReaction: j['my_reaction']?.toString(),
      replies: (j['replies'] is List)
          ? (j['replies'] as List)
                .whereType<Map>()
                .map(
                  (e) =>
                      EngagementComment.fromJson(Map<String, dynamic>.from(e)),
                )
                .toList()
          : const [],
    );
  }
}

Map<String, int> _reactionCounts(dynamic value) {
  if (value is! Map) return const {};
  return value.map((key, value) => MapEntry(key.toString(), _int(value)));
}

class GalleryItem {
  final int id;
  final String name;
  final String mime;
  final int size;
  final int parentId;
  final String kind;
  final DateTime? createdAt;
  final String viewUrl;
  final String downloadUrl;
  final String parentUrl;
  final String ownerName;
  final int? ownerId;
  final int? camera;
  final int? floor;

  GalleryItem({
    required this.id,
    required this.name,
    required this.mime,
    required this.size,
    required this.parentId,
    required this.kind,
    required this.createdAt,
    required this.viewUrl,
    required this.downloadUrl,
    required this.parentUrl,
    required this.ownerName,
    required this.ownerId,
    required this.camera,
    required this.floor,
  });

  bool get isImage => mime.startsWith('image/');
  bool get isVideo => mime.startsWith('video/');
  bool get isAudio => mime.startsWith('audio/');

  String viewPath() => viewUrl.isNotEmpty
      ? viewUrl
      : (kind == 'submission'
            ? '/api/submission-attachments/$id/view'
            : '/api/attachments/$id/view');

  String downloadPath() => downloadUrl.isNotEmpty
      ? downloadUrl
      : (kind == 'submission'
            ? '/api/submission-attachments/$id/download'
            : '/api/attachments/$id');

  static int? _optInt(dynamic v) {
    if (v == null) return null;
    if (v is int) return v;
    return int.tryParse(v.toString());
  }

  factory GalleryItem.fromJson(Map<String, dynamic> j) => GalleryItem(
    id: _int(j['id']),
    name: (j['original_name'] ?? j['name'] ?? '').toString(),
    mime: (j['mime_type'] ?? j['mime'] ?? '').toString(),
    size: _int(j['file_size'] ?? j['size']),
    parentId: _int(j['parent_id']),
    kind: (j['kind'] ?? 'note').toString(),
    createdAt: _dt(j['created_at'] ?? j['created']),
    viewUrl: (j['viewUrl'] ?? j['view_url'] ?? '').toString(),
    downloadUrl: (j['downloadUrl'] ?? j['download_url'] ?? '').toString(),
    parentUrl: (j['parentUrl'] ?? j['parent_url'] ?? '').toString(),
    ownerName: (j['owner_name'] ?? '').toString(),
    ownerId: _optInt(j['owner_id']),
    camera: _optInt(j['camera'] ?? j['camera_number']),
    floor: _optInt(j['floor'] ?? j['floor_number']),
  );
}

class AppNotification {
  final String id;
  final String title;
  final String body;
  final bool unread;
  final String? type;
  final String priority;
  final String category;
  final String? humanTime;
  final DateTime? createdAt;
  final Map<String, dynamic> data;

  AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.unread,
    required this.type,
    required this.priority,
    required this.category,
    required this.humanTime,
    required this.createdAt,
    required this.data,
  });

  factory AppNotification.fromJson(Map<String, dynamic> j) {
    final inner = j['data'] is Map
        ? Map<String, dynamic>.from(j['data'] as Map)
        : <String, dynamic>{};
    return AppNotification(
      id: (j['id'] ?? '').toString(),
      title: (j['title'] ?? inner['title'] ?? j['type'] ?? '').toString(),
      body: (j['body'] ?? j['message'] ?? inner['message'] ?? '').toString(),
      unread: j['read_at'] == null,
      type: (j['type'] ?? inner['type'])?.toString(),
      priority: (j['priority'] ?? inner['priority'] ?? 'normal').toString(),
      category: (j['category'] ?? inner['category'] ?? 'generic').toString(),
      humanTime: j['created_at_human']?.toString(),
      createdAt: _dt(j['created_at']),
      data: inner,
    );
  }
}

int _int(dynamic v, {int fallback = 0}) {
  if (v is int) return v;
  if (v is num) return v.toInt();
  return int.tryParse(v?.toString() ?? '') ?? fallback;
}

DateTime? _dt(dynamic v) {
  if (v == null) return null;
  if (v is DateTime) return v;
  return DateTime.tryParse(v.toString());
}
