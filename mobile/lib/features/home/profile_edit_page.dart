import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/avatar.dart';
import '../../core/config.dart';
import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/theme.dart';
import '../../core/validators.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../auth/auth_repo.dart';

class ProfileEditPage extends ConsumerStatefulWidget {
  const ProfileEditPage({super.key});

  @override
  ConsumerState<ProfileEditPage> createState() => _ProfileEditPageState();
}

class _ProfileEditPageState extends ConsumerState<ProfileEditPage> {
  late final TextEditingController _name;
  late final TextEditingController _username;
  late final TextEditingController _phone;
  final _curPass = TextEditingController();
  final _newPass = TextEditingController();
  final _confirmPass = TextEditingController();
  bool _showCur = false;
  bool _showNew = false;
  bool _showConfirm = false;
  String? _avatarPath;
  bool _removeAvatar = false;
  bool _busy = false;
  String? _error;
  final _fieldErrors = <String, String>{};

  @override
  void initState() {
    super.initState();
    final u = ref.read(authProvider).valueOrNull;
    _name = TextEditingController(text: u?.name ?? '');
    _username = TextEditingController(text: u?.username ?? '');
    _phone = TextEditingController(text: u?.personalNumber ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _username.dispose();
    _phone.dispose();
    _curPass.dispose();
    _newPass.dispose();
    _confirmPass.dispose();
    super.dispose();
  }

  bool get _wantsPasswordChange =>
      _curPass.text.isNotEmpty ||
      _newPass.text.isNotEmpty ||
      _confirmPass.text.isNotEmpty;

  String? _passwordError() {
    if (!_wantsPasswordChange) return null;
    if (_curPass.text.isEmpty) {
      return tr(ref, 'current_required');
    }
    if (_newPass.text.length < 8) {
      return tr(ref, 'password_min8');
    }
    if (_newPass.text != _confirmPass.text) {
      return tr(ref, 'password_mismatch');
    }
    return null;
  }

  Future<bool> _confirmPasswordChange() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const Icon(Icons.lock_rounded, size: 32),
        title: Text(tr(ref, 'change_password')),
        content: Text(
          tr(ref, 'password_change_confirm'),
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
    return ok == true;
  }

  String _avatarUrl(User? u) {
    final p = u?.avatarUrl ?? '';
    if (p.isEmpty) return '';
    if (p.startsWith('http')) return p;
    return '${ref.read(baseUrlProvider)}$p';
  }

  Future<void> _pick() async {
    final f = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1024,
      imageQuality: 85,
    );
    if (f != null) {
      setState(() {
        _avatarPath = f.path;
        _removeAvatar = false;
      });
    }
  }

  Future<void> _save() async {
    final nameError = V.profileName(_name.text);
    final usernameError = V.username(_username.text);
    final phoneError = V.syrianPhone(_phone.text);
    if (nameError != null || usernameError != null || phoneError != null) {
      setState(() {
        _fieldErrors
          ..clear()
          ..addAll({
            if (nameError != null) 'name': nameError,
            if (usernameError != null) 'username': usernameError,
            if (phoneError != null) 'personal_number': phoneError,
          });
        _error = null;
      });
      return;
    }
    final passErr = _passwordError();
    if (passErr != null) {
      setState(() => _error = passErr);
      return;
    }
    if (_wantsPasswordChange && !await _confirmPasswordChange()) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors.clear();
    });
    try {
      final user = await ref
          .read(authRepoProvider)
          .updateProfile(
            name: _name.text,
            username: _username.text,
            personalNumber: _phone.text.trim().isEmpty ? null : _phone.text,
            avatarPath: _avatarPath,
            removeAvatar: _removeAvatar && _avatarPath == null,
            currentPassword: _curPass.text.isEmpty ? null : _curPass.text,
            newPassword: _newPass.text.isEmpty ? null : _newPass.text,
          );
      ref.read(authProvider.notifier).setUser(user);
      await AvatarUrl.bump(ref);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(tr(ref, 'profile_saved'))));
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.userMessage;
        for (final k in [
          'name',
          'username',
          'personal_number',
          'avatar',
          'current_password',
          'password',
        ]) {
          final m = e.firstFieldError(k);
          if (m != null) _fieldErrors[k] = m;
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.toString();
      });
    }
  }

  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(
      text,
      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(authProvider).valueOrNull;
    final url = _avatarUrl(me);
    final showNet = url.isNotEmpty && _avatarPath == null;
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'edit_profile'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          Center(
            child: Column(
              children: [
                RasdAvatar(
                  url: showNet ? url : null,
                  name: me?.name ?? '?',
                  size: 112,
                  ringWidth: 3,
                  image: _avatarPath != null
                      ? FileImage(File(_avatarPath!))
                      : null,
                ),
                const SizedBox(height: 10),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextButton.icon(
                      onPressed: _busy ? null : _pick,
                      icon: const Icon(Icons.photo_camera_outlined, size: 18),
                      label: Text(tr(ref, 'change_avatar')),
                    ),
                    if (showNet || _avatarPath != null)
                      TextButton.icon(
                        onPressed: _busy
                            ? null
                            : () => setState(() {
                                _avatarPath = null;
                                _removeAvatar = true;
                              }),
                        icon: const Icon(Icons.delete_outline, size: 18),
                        style: TextButton.styleFrom(
                          foregroundColor: Theme.of(context).colorScheme.error,
                        ),
                        label: Text(tr(ref, 'remove_avatar')),
                      ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          _label(tr(ref, 'name')),
          TextField(
            spellCheckConfiguration: const SpellCheckConfiguration.disabled(),
            controller: _name,
            textInputAction: TextInputAction.next,
            onChanged: (_) => setState(() => _fieldErrors.remove('name')),
            decoration: InputDecoration(errorText: _fieldErrors['name']),
          ),
          const SizedBox(height: 12),
          _label(tr(ref, 'username')),
          TextField(
            spellCheckConfiguration: const SpellCheckConfiguration.disabled(),
            controller: _username,
            textDirection: TextDirection.ltr,
            textInputAction: TextInputAction.next,
            onChanged: (_) => setState(() => _fieldErrors.remove('username')),
            decoration: InputDecoration(errorText: _fieldErrors['username']),
          ),
          const SizedBox(height: 12),
          _label(tr(ref, 'personal_number')),
          TextField(
            spellCheckConfiguration: const SpellCheckConfiguration.disabled(),
            controller: _phone,
            keyboardType: TextInputType.phone,
            textDirection: TextDirection.ltr,
            onChanged: (_) =>
                setState(() => _fieldErrors.remove('personal_number')),
            decoration: InputDecoration(
              errorText: _fieldErrors['personal_number'],
              hintText: '09xxxxxxxx',
            ),
          ),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surfaceContainerHigh,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      Icons.lock_rounded,
                      size: 18,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      tr(ref, 'change_password'),
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  tr(ref, 'password_hint'),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 12),
                TextField(
                  spellCheckConfiguration:
                      const SpellCheckConfiguration.disabled(),
                  controller: _curPass,
                  obscureText: !_showCur,
                  decoration: InputDecoration(
                    labelText: tr(ref, 'current_password'),
                    prefixIcon: const Icon(Icons.key_outlined, size: 20),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _showCur
                            ? Icons.visibility_rounded
                            : Icons.visibility_off_rounded,
                        size: 20,
                      ),
                      onPressed: () => setState(() => _showCur = !_showCur),
                    ),
                    errorText: _fieldErrors['current_password'],
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  spellCheckConfiguration:
                      const SpellCheckConfiguration.disabled(),
                  controller: _newPass,
                  obscureText: !_showNew,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    labelText: tr(ref, 'new_password'),
                    prefixIcon: const Icon(
                      Icons.lock_outline_rounded,
                      size: 20,
                    ),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _showNew
                            ? Icons.visibility_rounded
                            : Icons.visibility_off_rounded,
                        size: 20,
                      ),
                      onPressed: () => setState(() => _showNew = !_showNew),
                    ),
                    errorText: _fieldErrors['password'],
                  ),
                ),
                const SizedBox(height: 10),
                _StrengthMeter(password: _newPass.text),
                const SizedBox(height: 12),
                TextField(
                  spellCheckConfiguration:
                      const SpellCheckConfiguration.disabled(),
                  controller: _confirmPass,
                  obscureText: !_showConfirm,
                  decoration: InputDecoration(
                    labelText: tr(ref, 'confirm_password'),
                    prefixIcon: const Icon(Icons.verified_outlined, size: 20),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _showConfirm
                            ? Icons.visibility_rounded
                            : Icons.visibility_off_rounded,
                        size: 20,
                      ),
                      onPressed: () =>
                          setState(() => _showConfirm = !_showConfirm),
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(
              _error!,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: 13,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
          const SizedBox(height: 20),
          FilledButton(
            onPressed: _busy ? null : _save,
            child: _busy
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : Text(tr(ref, 'save')),
          ),
        ],
      ),
    );
  }

}

class _StrengthMeter extends ConsumerWidget {
  final String password;
  const _StrengthMeter({required this.password});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (password.isEmpty) return const SizedBox.shrink();
    var score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (RegExp(r'[0-9]').hasMatch(password)) score++;
    if (RegExp(r'[^a-zA-Z0-9]').hasMatch(password)) score++;
    final frac = (score / 4).clamp(0.0, 1.0);
    final color = score <= 1
        ? Colors.red
        : score == 2
        ? Colors.amber.shade700
        : score == 3
        ? Colors.lightGreen
        : RasdColors.green;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(99),
          child: LinearProgressIndicator(
            value: frac == 0 ? 0.05 : frac,
            minHeight: 6,
            backgroundColor: Theme.of(
              context,
            ).colorScheme.surfaceContainerHighest,
            valueColor: AlwaysStoppedAnimation(color),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          [
            '—',
            tr(ref, 'pw_weak'),
            tr(ref, 'pw_medium'),
            tr(ref, 'pw_good'),
            tr(ref, 'pw_strong'),
          ][score],
          style: TextStyle(
            fontSize: 11.5,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
      ],
    );
  }
}
