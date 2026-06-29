<form method="POST"
      action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}"
      class="max-w-xl space-y-5">
    @csrf
    @if($user) @method('PUT') @endif

    <x-card title="Data Pengguna">
        <div class="space-y-4">
            <x-input name="name" label="Nama Lengkap" :value="$user?->name" required />
            <x-input name="username" label="Username" :value="$user?->username" hint="Huruf, angka, - dan _ saja." required />
            <x-input name="email" type="email" label="Email (opsional)" :value="$user?->email" />

            <x-select name="role" label="Role">
                <option value="employee" @selected(old('role', $user?->role?->value ?? 'employee') === 'employee')>Karyawan</option>
                <option value="admin" @selected(old('role', $user?->role?->value) === 'admin')>Admin</option>
            </x-select>

            <label class="flex items-center gap-3 text-sm text-slate-700 select-none">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user?->is_active ?? true))
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Akun aktif (bisa login)
            </label>
        </div>
    </x-card>

    <x-card title="Kata Sandi" subtitle="{{ $user ? 'Kosongkan jika tidak ingin mengubah.' : 'Wajib diisi untuk akun baru.' }}">
        <div class="space-y-4">
            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" {{ $user ? '' : 'required' }} autocomplete="new-password" class="field-input">
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Konfirmasi Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="field-input">
            </div>
        </div>
    </x-card>

    <div class="flex gap-3">
        <x-button type="submit">Simpan</x-button>
        <x-button href="{{ route('admin.users.index') }}" variant="secondary">Batal</x-button>
    </div>
</form>
