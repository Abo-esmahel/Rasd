import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../home/user_profile_page.dart';
import 'managed_users_repo.dart';

class ManageUsersPage extends ConsumerStatefulWidget {
  const ManageUsersPage({super.key});

  @override
  ConsumerState<ManageUsersPage> createState() => _ManageUsersPageState();
}

class _ManageUsersPageState extends ConsumerState<ManageUsersPage> {
  final _search = TextEditingController();
  List<ManagedUser> _users = const [];
  UsersFilter _filter = UsersFilter.all;
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await ref
          .read(managedUsersProvider)
          .list(query: _search.text, filter: _filter);
      if (!mounted) return;
      setState(() {
        _users = list;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  void _replace(ManagedUser updated) {
    setState(() {
      _users = _users
          .map((u) => u.id == updated.id ? updated : u)
          .toList(growable: false);
    });
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } catch (e) {
      _snack(friendlyErrorText(e, ref.read(localeProvider)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openAdd() async {
    final done = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _UserFormSheet(),
    );
    if (done == true) {
      _snack(tr(ref, 'user_added'));
      await _load();
    }
  }

  Future<void> _openEdit(ManagedUser u) async {
    final done = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _UserFormSheet(existing: u),
    );
    if (done == true) {
      _snack(tr(ref, 'user_updated'));
      await _load();
    }
  }

  Future<void> _confirmBan(ManagedUser u) async {
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => const _BanReasonDialog(),
    );
    if (reason == null || !mounted) return;

    await _run(() async {
      final updated = await ref
          .read(managedUsersProvider)
          .ban(u.id, reason: reason);
      _replace(updated);
      _snack(tr(ref, 'user_banned_done'));
    });
  }

  Future<void> _confirmUnban(ManagedUser u) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const Icon(Icons.lock_open_rounded, size: 32),
        title: Text(tr(ref, 'user_unban')),
        content: Text(
          u.banReason == null || u.banReason!.isEmpty
              ? tr(ref, 'user_name')
              : '${tr(ref, 'user_ban_reason')}: ${u.banReason}',
          textAlign: TextAlign.center,
          style: const TextStyle(height: 1.8),
        ),
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
    if (ok != true || !mounted) return;

    await _run(() async {
      final updated = await ref.read(managedUsersProvider).unban(u.id);
      _replace(updated);
      _snack(tr(ref, 'user_unbanned_done'));
    });
  }

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final chips = <UsersFilter, String>{
      UsersFilter.all: tr(ref, 'filter_all'),
      UsersFilter.writers: tr(ref, 'filter_writers'),
      UsersFilter.monitors: tr(ref, 'filter_monitors'),
      UsersFilter.banned: tr(ref, 'filter_banned'),
    };

    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'manage_users'))),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _busy ? null : _openAdd,
        icon: const Icon(Icons.person_add_alt_1_rounded),
        label: Text(tr(ref, 'add_user')),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onChanged: (_) => _load(),
              decoration: InputDecoration(
                hintText: tr(ref, 'search_users_hint'),
                prefixIcon: const Icon(Icons.search_rounded, size: 20),
                isDense: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(RasdRadii.input),
                ),
              ),
            ),
          ),
          SizedBox(
            height: 38,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final f in UsersFilter.values)
                  Padding(
                    padding: const EdgeInsets.only(left: 8),
                    child: _FilterChip(
                      label: chips[f]!,
                      selected: _filter == f,
                      onTap: () {
                        setState(() => _filter = f);
                        _load();
                      },
                    ),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Row(
              children: [
                Text(
                  tr(
                    ref,
                    'users_count',
                  ).replaceFirst(':count', '${_users.length}'),
                  style: TextStyle(
                    fontSize: 12,
                    color: dark ? RasdColors.darkInk2 : RasdColors.ink3,
                  ),
                ),
              ],
            ),
          ),
          Expanded(child: _body(dark)),
        ],
      ),
    );
  }

  Widget _body(bool dark) {
    if (_loading) {
      return const RasdSkeletonList();
    }
    if (_error != null) {
      return RasdError(
        error: _error!,
        onRetry: _load,
        retryLabel: tr(ref, 'retry'),
      );
    }
    if (_users.isEmpty) {
      final searching = _search.text.trim().isNotEmpty;
      return RasdEmpty(
        icon: searching ? Icons.search_off_rounded : Icons.group_outlined,
        message: searching
            ? tr(ref, 'users_no_results')
            : '${tr(ref, 'users_empty')}\n${tr(ref, 'users_empty_hint')}',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
        itemCount: _users.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (_, i) => _UserRow(
          user: _users[i],
          busy: _busy,
          onEdit: () => _openEdit(_users[i]),
          onBan: () => _confirmBan(_users[i]),
          onUnban: () => _confirmUnban(_users[i]),
        ),
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final bg = selected
        ? (dark ? const Color(0xFF1E3328) : RasdColors.greenSoft)
        : (dark ? RasdColors.darkMuted : const Color(0xFFFFFFFF));
    final fg = selected
        ? (dark ? const Color(0xFF4ADE80) : RasdColors.green)
        : (dark ? RasdColors.darkInk2 : RasdColors.ink2);

    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(RasdRadii.chip),
      child: InkWell(
        borderRadius: BorderRadius.circular(RasdRadii.chip),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(RasdRadii.chip),
            border: Border.all(
              color: selected
                  ? (dark ? const Color(0xFF2F6B45) : RasdColors.green)
                  : (dark ? RasdColors.darkBorder : RasdColors.border),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
              color: fg,
            ),
          ),
        ),
      ),
    );
  }
}

class _UserRow extends ConsumerWidget {
  final ManagedUser user;
  final bool busy;
  final VoidCallback onEdit;
  final VoidCallback onBan;
  final VoidCallback onUnban;
  const _UserRow({
    required this.user,
    required this.busy,
    required this.onEdit,
    required this.onBan,
    required this.onUnban,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final muted = dark ? RasdColors.darkInk2 : RasdColors.ink3;

    return Opacity(
      opacity: busy ? 0.6 : 1,
      child: RasdCard(
        onTap: busy ? null : onEdit,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            RasdUserChip(
              userId: user.id,
              avatarUrl: user.avatarUrl,
              name: user.name,
              size: 42,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: RasdUserChip(
                          userId: user.id,
                          name: user.name,
                          showAvatar: false,
                          nameStyle: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                      ),
                      if (user.banned) ...[
                        const SizedBox(width: 8),
                        _MiniBadge(
                          label: tr(ref, 'user_banned_badge'),
                          bg: const Color(0xFFFDECEC),
                          fg: const Color(0xFFB42323),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '@${user.username}',
                    style: TextStyle(fontSize: 12.5, color: muted),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      _MiniBadge(
                        label: tr(
                          ref,
                          user.isWriter ? 'role_writer' : 'role_monitor',
                        ),
                        bg: dark
                            ? const Color(0xFF1E3328)
                            : RasdColors.greenSoft,
                        fg: dark ? const Color(0xFF4ADE80) : RasdColors.green,
                      ),
                      if (user.personalNumber != null &&
                          user.personalNumber!.isNotEmpty)
                        Text(
                          user.personalNumber!,
                          textDirection: TextDirection.ltr,
                          style: TextStyle(fontSize: 12, color: muted),
                        ),
                    ],
                  ),
                  if (user.banned && user.banReason != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      '${tr(ref, 'user_ban_reason')}: ${user.banReason}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFFB42323),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            PopupMenuButton<String>(
              enabled: !busy,
              onSelected: (v) {
                if (v == 'edit') onEdit();
                if (v == 'ban') onBan();
                if (v == 'unban') onUnban();
              },
              itemBuilder: (_) => [
                PopupMenuItem(
                  value: 'edit',
                  child: Row(
                    children: [
                      const Icon(Icons.edit_outlined, size: 18),
                      const SizedBox(width: 8),
                      Text(tr(ref, 'edit_user')),
                    ],
                  ),
                ),
                PopupMenuItem(
                  value: user.banned ? 'unban' : 'ban',
                  child: Row(
                    children: [
                      Icon(
                        user.banned
                            ? Icons.lock_open_rounded
                            : Icons.block_rounded,
                        size: 18,
                        color: user.banned ? null : const Color(0xFFB42323),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        tr(ref, user.banned ? 'user_unban' : 'user_ban'),
                        style: TextStyle(
                          color: user.banned ? null : const Color(0xFFB42323),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MiniBadge extends StatelessWidget {
  final String label;
  final Color bg;
  final Color fg;
  const _MiniBadge({required this.label, required this.bg, required this.fg});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 2),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(RasdRadii.chip),
      ),
      child: Text(
        label,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: fg),
      ),
    );
  }
}

class _UserFormSheet extends ConsumerStatefulWidget {
  final ManagedUser? existing;
  const _UserFormSheet({this.existing});

  @override
  ConsumerState<_UserFormSheet> createState() => _UserFormSheetState();
}

class _UserFormSheetState extends ConsumerState<_UserFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _username;
  late final TextEditingController _phone;
  final _password = TextEditingController();
  late String _role;
  bool _busy = false;
  bool _obscure = true;

  bool get _isNew => widget.existing == null;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?.name ?? '');
    _username = TextEditingController(text: e?.username ?? '');
    _phone = TextEditingController(text: e?.personalNumber ?? '');
    _role = e?.role ?? 'monitor';
  }

  @override
  void dispose() {
    _name.dispose();
    _username.dispose();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _busy = true);
    try {
      final repo = ref.read(managedUsersProvider);
      if (_isNew) {
        await repo.create(
          name: _name.text,
          username: _username.text,
          password: _password.text,
          role: _role,
          personalNumber: _phone.text,
        );
      } else {
        await repo.update(
          widget.existing!.id,
          name: _name.text,
          username: _username.text,
          role: _role,
          personalNumber: _phone.text,
        );
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(friendlyErrorText(e, ref.read(localeProvider)))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Container(
        decoration: BoxDecoration(
          color: dark ? RasdColors.darkCard : RasdColors.card,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
        ),
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 22),
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(
                      color: dark ? RasdColors.darkBorder : RasdColors.border,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                Text(
                  tr(ref, _isNew ? 'add_user' : 'edit_user'),
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _name,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: tr(ref, 'user_name')),
                  validator: (v) => (v == null || v.trim().isEmpty)
                      ? tr(ref, 'complete_fields')
                      : null,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _username,
                  textInputAction: TextInputAction.next,
                  autocorrect: false,
                  decoration: InputDecoration(
                    labelText: tr(ref, 'user_username'),
                  ),
                  validator: (v) {
                    final s = (v ?? '').trim();
                    if (s.isEmpty) return tr(ref, 'complete_fields');
                    if (!RegExp(r'^[A-Za-z0-9._-]+$').hasMatch(s)) {
                      return tr(ref, 'complete_fields');
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: tr(ref, 'user_phone')),
                ),
                if (_isNew) ...[
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _password,
                    obscureText: _obscure,
                    textInputAction: TextInputAction.done,
                    decoration: InputDecoration(
                      labelText: tr(ref, 'user_password'),
                      helperText: tr(ref, 'user_password_hint'),
                      helperMaxLines: 2,
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscure
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                          size: 20,
                        ),
                        onPressed: () => setState(() => _obscure = !_obscure),
                      ),
                    ),
                    validator: (v) => (v == null || v.length < 6)
                        ? tr(ref, 'complete_fields')
                        : null,
                  ),
                ],
                const SizedBox(height: 12),
                Text(
                  tr(ref, 'user_role'),
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: dark ? RasdColors.darkInk2 : RasdColors.ink2,
                  ),
                ),
                const SizedBox(height: 6),
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'monitor',
                      label: Text(tr(ref, 'role_monitor')),
                      icon: const Icon(Icons.videocam_outlined, size: 16),
                    ),
                    ButtonSegment(
                      value: 'report_writer',
                      label: Text(tr(ref, 'role_writer')),
                      icon: const Icon(Icons.edit_note_rounded, size: 16),
                    ),
                  ],
                  selected: {_role},
                  onSelectionChanged: (s) => setState(() => _role = s.first),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(tr(ref, _isNew ? 'add_user' : 'edit_user')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BanReasonDialog extends ConsumerStatefulWidget {
  const _BanReasonDialog();

  @override
  ConsumerState<_BanReasonDialog> createState() => _BanReasonDialogState();
}

class _BanReasonDialogState extends ConsumerState<_BanReasonDialog> {
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      icon: const Icon(Icons.block_rounded, size: 32, color: Color(0xFFB42323)),
      title: Text(tr(ref, 'user_ban')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            tr(ref, 'user_ban_reason_hint'),
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12.5, height: 1.7),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reason,
            maxLength: 300,
            maxLines: 3,
            decoration: InputDecoration(labelText: tr(ref, 'user_ban_reason')),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(tr(ref, 'cancel')),
        ),
        FilledButton(
          style: FilledButton.styleFrom(
            backgroundColor: const Color(0xFFB42323),
          ),
          onPressed: () => Navigator.pop(context, _reason.text.trim()),
          child: Text(tr(ref, 'user_ban')),
        ),
      ],
    );
  }
}
