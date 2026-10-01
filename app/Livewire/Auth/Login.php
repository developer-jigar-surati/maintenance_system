<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Login extends Component
{
    #[Validate('required|string')]
    public string $identifier = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        // Residents remember a phone number more reliably than an email, so
        // either is accepted as the identifier.
        $field = filter_var($this->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([$field => $this->identifier, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identifier' => __('Those credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $user = Auth::user();

        if ($user->status === 'suspended') {
            Auth::logout();

            throw ValidationException::withMessages([
                'identifier' => __('This account has been suspended. Please contact your committee.'),
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        $this->redirectIntended(route('dashboard'), navigate: true);
    }

    /** Five attempts per identifier and IP, then a cooling-off period. */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->identifier).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
