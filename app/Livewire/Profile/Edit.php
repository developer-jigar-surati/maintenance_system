<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Edit extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
    }

    public function saveProfile(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        // Changing the address invalidates the previous verification.
        if ($validated['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->forceFill($validated)->save();

        $this->dispatch('notify', message: 'Profile updated.', tone: 'positive');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($this->currentPassword, auth()->user()->password)) {
            $this->addError('currentPassword', __('That password is not correct.'));

            return;
        }

        auth()->user()->forceFill(['password' => $this->password])->save();

        $this->reset(['currentPassword', 'password', 'password_confirmation']);
        $this->dispatch('notify', message: 'Password changed.', tone: 'positive');
    }

    public function render()
    {
        return view('livewire.profile.edit', [
            'user' => auth()->user(),
            'societies' => auth()->user()->activeSocieties()->get(),
        ])->title('Your profile');
    }
}
