import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../home/user_profile_page.dart';
import '../notes/notes_page.dart';
import '../notes/notes_providers.dart';
import '../notes/notes_repo.dart';
import 'report_detail_page.dart';
import 'report_insights_page.dart';
import 'sheet_art.dart';
import 'reports_providers.dart';
import 'reports_repo.dart';

class ReportsPage extends ConsumerStatefulWidget {
  const ReportsPage({super.key});

  @override
  ConsumerState<ReportsPage> createState() => _ReportsPageState();
}

class _ReportsPageState extends ConsumerState<ReportsPage> {
  final _scroll = ScrollController();
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) {
        ref.read(reportsListProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onSearch(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      final q = v.trim();
      ref.read(reportFilterProvider.notifier).state = ref
          .read(reportFilterProvider)
          .copyWith(q: q.isEmpty ? null : q, clearQ: q.isEmpty);
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).valueOrNull;
    if (user == null || (!user.isWriter && !user.isGM)) {
      return Scaffold(body: Center(child: Text(tr(ref, 'forbidden'))));
    }
    final list = ref.watch(reportsListProvider);
    final filter = ref.watch(reportFilterProvider);
    final activeFilters =
        (filter.status != null ? 1 : 0) +
        (filter.date != null ? 1 : 0) +
        (filter.q != null && filter.q!.trim().length >= 2 ? 1 : 0);
    return RefreshIndicator(
      onRefresh: () => ref.read(reportsListProvider.notifier).refresh(),
      child: list.when(
        loading: () => const RasdSkeletonList(),
        error: (e, _) => RasdError(
          error: e,
          onRetry: () => ref.read(reportsListProvider.notifier).refresh(),
          retryLabel: tr(ref, 'retry'),
        ),
        data: (items) {
          final pub = items.where((r) => r.isPublished).length;
          final drafts = user.isWriter ? items.length - pub : null;
          return ListView(
            controller: _scroll,
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 88),
            children: [
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _searchCtrl,
                      onChanged: _onSearch,
                      textInputAction: TextInputAction.search,
                      decoration: InputDecoration(
                        hintText: tr(ref, 'search'),
                        prefixIcon: const Icon(Icons.search_outlined, size: 20),
                        suffixIcon: filter.q == null
                            ? null
                            : IconButton(
                                icon: const Icon(Icons.clear, size: 18),
                                onPressed: () {
                                  _searchCtrl.clear();
                                  ref
                                      .read(reportFilterProvider.notifier)
                                      .state = filter.copyWith(
                                    clearQ: true,
                                  );
                                },
                              ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  if (user.isWriter || user.isGM) ...[
                    IconButton.filledTonal(
                      tooltip: tr(ref, 'insights'),
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const ReportInsightsPage(),
                        ),
                      ),
                      icon: const Icon(Icons.insights_outlined, size: 20),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Badge(
                    isLabelVisible: activeFilters > 0,
                    label: Text('$activeFilters'),
                    child: IconButton.filledTonal(
                      tooltip: tr(ref, 'filter'),
                      onPressed: () =>
                          _openFilter(context, filter, user.isWriter),
                      icon: const Icon(Icons.filter_list_outlined, size: 20),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: RasdStat(
                      icon: Icons.description_outlined,
                      value: '${items.length}',
                      label: tr(ref, 'total'),
                      color: RasdColors.green,
                      soft: RasdColors.greenSoft,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: RasdStat(
                      icon: Icons.verified_outlined,
                      value: '$pub',
                      label: tr(ref, 'published'),
                      color: RasdColors.green,
                      soft: RasdColors.greenSoft,
                    ),
                  ),
                  const SizedBox(width: 10),
                  if (drafts != null) ...[
                    const SizedBox(width: 10),
                    Expanded(
                      child: RasdStat(
                        icon: Icons.edit_outlined,
                        value: '$drafts',
                        label: tr(ref, 'draft'),
                        color: RasdColors.goldInk,
                        soft: RasdColors.goldSoft,
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 12),
              if (items.isEmpty)
                RasdEmpty(message: tr(ref, 'no_results'))
              else
                for (var i = 0; i < items.length; i++)
                  _ReportCard(report: items[i]),
            ],
          );
        },
      ),
    );
  }

  Future<void> _openFilter(
    BuildContext context,
    ReportFilter filter,
    bool allowDrafts,
  ) async {
    final dateCtrl = TextEditingController(text: filter.date ?? '');
    String? status = filter.status;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            16,
            16,
            16 + MediaQuery.of(ctx).viewInsets.bottom,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SegmentedButton<String?>(
                segments: [
                  ButtonSegment(value: null, label: Text(tr(ref, 'all'))),
                  ButtonSegment(
                    value: 'published',
                    label: Text(tr(ref, 'published')),
                  ),
                  if (allowDrafts)
                    ButtonSegment(
                      value: 'draft',
                      label: Text(tr(ref, 'draft')),
                    ),
                ],
                selected: {status},
                onSelectionChanged: (s) => setS(() => status = s.first),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: dateCtrl,
                readOnly: true,
                decoration: InputDecoration(
                  labelText: tr(ref, 'date'),
                  prefixIcon: const Icon(Icons.calendar_today_outlined),
                  suffixIcon: dateCtrl.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.clear, size: 18),
                          onPressed: () => setS(() => dateCtrl.clear()),
                        ),
                ),
                onTap: () async {
                  final now = DateTime.now();
                  final picked = await showDatePicker(
                    context: ctx,
                    initialDate: DateTime.tryParse(dateCtrl.text) ?? now,
                    firstDate: DateTime(2020),
                    lastDate: now,
                  );
                  if (picked != null) {
                    setS(
                      () => dateCtrl.text =
                          '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}',
                    );
                  }
                },
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: FilledButton(
                      onPressed: () {
                        ref
                            .read(reportFilterProvider.notifier)
                            .state = filter.copyWith(
                          status: status,
                          clearStatus: status == null,
                          date: dateCtrl.text.isEmpty ? null : dateCtrl.text,
                          clearDate: dateCtrl.text.isEmpty,
                        );
                        Navigator.pop(ctx);
                      },
                      child: Text(tr(ref, 'apply')),
                    ),
                  ),
                  const SizedBox(width: 12),
                  TextButton(
                    onPressed: () {
                      ref.read(reportFilterProvider.notifier).state = filter
                          .copyWith(clearStatus: true, clearDate: true);
                      Navigator.pop(ctx);
                    },
                    child: Text(tr(ref, 'clear')),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReportCard extends ConsumerWidget {
  final Report report;
  const _ReportCard({required this.report});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r = report;
    final user = ref.watch(authProvider).valueOrNull;
    final count = r.notesCount ?? r.notes.length;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => ReportDetailPage(id: r.id)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Stack(
                children: [
                  SheetCover(report: r),
                  PositionedDirectional(
                    bottom: 10,
                    end: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: .55),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.note_alt_outlined,
                            size: 13,
                            color: Colors.white,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            '$count',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            r.title.isEmpty ? '—' : r.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        RasdStatusChip(
                          status: r.isPublished ? 'published' : 'draft',
                          label: tr(ref, r.isPublished ? 'published' : 'draft'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        const Icon(Icons.calendar_today_outlined, size: 13),
                        const SizedBox(width: 5),
                        Text(
                          r.reportDate,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        if ((r.author?.name ?? '').isNotEmpty) ...[
                          const SizedBox(width: 10),
                          const Icon(Icons.person_outline, size: 13),
                          const SizedBox(width: 5),
                          Expanded(
                            child: RasdUserChip(
                              userId: r.author?.id,
                              name: r.author!.name,
                              showAvatar: false,
                              nameStyle: Theme.of(context).textTheme.bodySmall,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 9),
                    Wrap(
                      spacing: 7,
                      runSpacing: 5,
                      children: [
                        if (r.isDraft)
                          _ReportMeta(label: tr(ref, 'my_report'))
                        else if (r.authorId == user?.id)
                          _ReportMeta(label: tr(ref, 'my_report'))
                        else
                          _ReportMeta(label: tr(ref, 'shared_report')),
                        if (r.commentsCount > 0)
                          _ReportMeta(
                            icon: Icons.chat_bubble_outline,
                            label: '${r.commentsCount}',
                          ),
                        if (r.reactionsCount > 0)
                          _ReportMeta(
                            icon: Icons.favorite_border,
                            label: '${r.reactionsCount}',
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReportMeta extends StatelessWidget {
  final String label;
  final IconData? icon;
  const _ReportMeta({required this.label, this.icon});
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(99),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 13), const SizedBox(width: 4)],
          Text(label, style: Theme.of(context).textTheme.labelSmall),
        ],
      ),
    ),
  );
}

class ReportCreateSheet extends ConsumerStatefulWidget {
  const ReportCreateSheet({super.key});

  @override
  ConsumerState<ReportCreateSheet> createState() => _ReportCreateSheetState();
}

class _ReportCreateSheetState extends ConsumerState<ReportCreateSheet> {
  final _form = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _contentCtrl = TextEditingController();
  DateTime _date = DateTime.now();
  bool _busy = false;
  bool _aiBusy = false;
  bool _aiOutOfDate = false;
  List<int> _aiPreviewNoteIds = [];
  String? _error;
  Map<String, dynamic>? _dailyStatus;
  bool _statusBusy = true;
  bool _statusError = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadDailyStatus());
  }

  String get _dateString =>
      '${_date.year}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}';

  int _statusCount(String key) => (_dailyStatus?[key] as num?)?.toInt() ?? 0;
  bool get _reportExists => _dailyStatus?['report_exists'] == true;
  bool get _canCreate =>
      !_statusBusy && !_statusError && _dailyStatus?['can_create'] == true;

  Future<void> _loadDailyStatus() async {
    if (mounted) {
      setState(() {
        _statusBusy = true;
        _statusError = false;
      });
    }
    try {
      final data = await ref.read(reportsRepoProvider).dailyStatus(_dateString);
      if (!mounted) return;
      final acceptedIds = ((data['accepted_note_ids'] as List?) ?? [])
          .map((id) => int.tryParse(id.toString()) ?? 0)
          .where((id) => id > 0)
          .toSet();
      final previewIds = _aiPreviewNoteIds.toSet();
      final previewChanged =
          _aiPreviewNoteIds.isNotEmpty &&
          _statusInt(data['pending_count']) == 0 &&
          (acceptedIds.length != previewIds.length ||
              !acceptedIds.containsAll(previewIds));
      setState(() {
        _dailyStatus = data;
        _statusBusy = false;
        _statusError = false;
        if (previewChanged) _aiOutOfDate = true;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _statusBusy = false;
        _statusError = true;
      });
    }
  }

  Future<void> _openPendingNotes() async {
    final navigator = Navigator.of(context);
    final date = _dateString;
    ref.read(noteFilterProvider.notifier).state = NoteFilter(
      status: 'pending',
      date: date,
    );
    navigator.pop();
    await Future<void>.delayed(Duration.zero);
    navigator.push(
      MaterialPageRoute<void>(
        builder: (_) => Scaffold(
          appBar: AppBar(title: Text(tr(ref, 'pending_notes_title'))),
          body: const NotesPage(),
        ),
      ),
    );
  }

  Future<void> _draftWithAi() async {
    if (_titleCtrl.text.trim().isNotEmpty &&
        _titleCtrl.text.trim().length < 3) {
      setState(() => _error = tr(ref, 'title_length'));
      return;
    }
    if (_contentCtrl.text.trim().isNotEmpty) {
      final replace = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          content: Text(tr(ref, 'ai_suggestion_overwrite')),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: Text(tr(ref, 'cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: Text(tr(ref, 'confirm')),
            ),
          ],
        ),
      );
      if (replace != true || !mounted) return;
    }
    setState(() {
      _aiBusy = true;
      _error = null;
    });
    try {
      final data = await ref
          .read(reportsRepoProvider)
          .preview(date: _dateString, title: _titleCtrl.text);
      final content = (data['content'] ?? '').toString();
      if (content.trim().isEmpty) throw Exception(tr(ref, 'ai_preview_empty'));
      if (!mounted) return;
      setState(() {
        _contentCtrl.text = content;
        _aiPreviewNoteIds = ((data['note_ids'] as List?) ?? [])
            .map((id) => int.tryParse(id.toString()) ?? 0)
            .where((id) => id > 0)
            .toList();
        _aiOutOfDate = false;
        if (_titleCtrl.text.trim().isEmpty &&
            (data['title'] ?? '').toString().length <= 255) {
          _titleCtrl.text = (data['title'] ?? '').toString();
        }
        _aiBusy = false;
      });
      await _loadDailyStatus();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _aiOutOfDate
                ? tr(ref, 'ai_suggestion_stale')
                : (_statusInt(data['pending_count']) > 0)
                ? tr(
                    ref,
                    'ai_suggestion_pending',
                    args: {'n': '${data['pending_count']}'},
                  )
                : '${tr(ref, 'ai_suggestion_ready')} (${data['note_count'] ?? 0})',
          ),
        ),
      );
    } catch (e) {
      if (mounted) {
        setState(() {
          _aiBusy = false;
          _error = e.toString();
        });
      }
    }
  }

  int _statusInt(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  Widget _dailyStatusCard(BuildContext context) {
    final theme = Theme.of(context);
    final warning =
        _statusError || _reportExists || _statusCount('pending_count') > 0;
    final color = warning ? theme.colorScheme.error : theme.colorScheme.primary;
    String message;
    if (_statusError) {
      message = tr(ref, 'report_day_status_error');
    } else if (_reportExists) {
      message = tr(ref, 'report_day_status_exists');
    } else if (_statusCount('pending_count') > 0) {
      message = tr(
        ref,
        'report_day_status_pending',
        args: {'n': '${_statusCount('pending_count')}'},
      );
    } else if (_statusCount('accepted_count') > 0) {
      message = tr(
        ref,
        'report_day_status_ready',
        args: {'n': '${_statusCount('accepted_count')}'},
      );
    } else {
      message = tr(ref, 'report_day_status_empty');
    }

    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .07),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: .22)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_statusBusy)
            SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2, color: color),
            )
          else
            Icon(
              warning ? Icons.rule_outlined : Icons.fact_check_outlined,
              size: 19,
              color: color,
            ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _statusBusy ? tr(ref, 'report_day_status_loading') : message,
                  style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
                ),
                if (!_statusBusy &&
                    !_statusError &&
                    !_reportExists &&
                    _statusCount('pending_count') > 0)
                  Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: TextButton.icon(
                      onPressed: _busy || _aiBusy ? null : _openPendingNotes,
                      icon: const Icon(Icons.arrow_forward_outlined, size: 16),
                      label: Text(tr(ref, 'review_notes')),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        16,
        16,
        16 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: Theme.of(
                        context,
                      ).colorScheme.primary.withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: Icon(
                      Icons.description_outlined,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          tr(ref, 'new_report'),
                          style: const TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          tr(ref, 'reports_subtitle'),
                          style: TextStyle(
                            fontSize: 12,
                            color: Theme.of(
                              context,
                            ).colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              TextFormField(
                spellCheckConfiguration:
                    const SpellCheckConfiguration.disabled(),
                controller: _titleCtrl,
                validator: (v) {
                  final t = (v ?? '').trim();
                  if (t.isNotEmpty && (t.length < 3 || t.length > 255)) {
                    return tr(ref, 'title_length');
                  }
                  return null;
                },
                decoration: InputDecoration(
                  labelText: '${tr(ref, 'title')} (${tr(ref, 'optional')})',
                ),
              ),
              TextFormField(
                spellCheckConfiguration:
                    const SpellCheckConfiguration.disabled(),
                controller: _contentCtrl,
                minLines: 3,
                maxLines: 7,
                maxLength: 20000,
                decoration: InputDecoration(
                  labelText: tr(ref, 'content_optional'),
                ),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.calendar_today_outlined),
                title: Text(_dateString),
                onTap: () async {
                  final p = await showDatePicker(
                    context: context,
                    initialDate: _date,
                    firstDate: DateTime(2020),
                    lastDate: DateTime.now(),
                  );
                  if (p != null && p != _date) {
                    setState(() {
                      _date = p;
                      _aiOutOfDate = _contentCtrl.text.trim().isNotEmpty;
                      _aiPreviewNoteIds = [];
                      _error = null;
                    });
                    unawaited(_loadDailyStatus());
                  }
                },
              ),
              _dailyStatusCard(context),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: _busy || _aiBusy || _statusBusy || _reportExists
                    ? null
                    : _draftWithAi,
                icon: _aiBusy
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.auto_awesome_outlined, size: 18),
                label: Text(
                  _aiBusy
                      ? tr(ref, 'ai_generating_suggestion')
                      : tr(ref, 'ai_generate_suggestion'),
                ),
              ),
              if (_aiOutOfDate)
                Text(
                  tr(ref, 'ai_suggestion_stale'),
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.error,
                    fontSize: 12,
                  ),
                ),
              if (_error != null)
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: _busy || _aiBusy || !_canCreate || _aiOutOfDate
                    ? null
                    : () async {
                        if (!_form.currentState!.validate()) {
                          return;
                        }
                        setState(() {
                          _busy = true;
                          _error = null;
                        });
                        try {
                          await ref
                              .read(reportsRepoProvider)
                              .create(
                                date: _dateString,
                                title: _titleCtrl.text,
                                content: _contentCtrl.text,
                                aiPreviewNoteIds: _aiPreviewNoteIds,
                              );
                          ref.read(reportsListProvider.notifier).refresh();
                          if (context.mounted) {
                            Navigator.pop(context);
                          }
                        } catch (e) {
                          await _loadDailyStatus();
                          if (!mounted) return;
                          setState(() {
                            _busy = false;
                            _error = e.toString();
                          });
                        }
                      },
                child: Text(tr(ref, 'new_report')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
