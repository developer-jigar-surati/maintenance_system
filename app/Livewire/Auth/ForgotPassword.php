<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class ForgotPassword extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    public function sendLink(): void
    {
        $this->validate();

        Password::sendResetLink(['email' => $this->email]);

        // Always report success: telling an anonymous visitor whether an
        // address is registered would leak the society's membership.
        session()->flash('status', __('If that address is registered, a reset link is on its way.'));

        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
