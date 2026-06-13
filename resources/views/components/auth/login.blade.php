<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    /** Tentative de connexion avec throttling (5 essais / minute par email+IP). */
    public function connexion()
    {
        $this->validate(
            [
                'email'    => ['required', 'email'],
                'password' => ['required'],
            ],
            [
                'email.required'    => 'L’adresse e-mail est obligatoire.',
                'email.email'       => 'Adresse e-mail invalide.',
                'password.required' => 'Le mot de passe est obligatoire.',
            ],
        );

        $this->verifierThrottle();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->cleThrottle());
            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides.',
            ]);
        }

        RateLimiter::clear($this->cleThrottle());
        session()->regenerate();

        return redirect()->intended('/inventory');
    }

    /** Bloque après trop de tentatives ratées. */
    private function verifierThrottle(): void
    {
        if (! RateLimiter::tooManyAttempts($this->cleThrottle(), 5)) {
            return;
        }

        $secondes = RateLimiter::availableIn($this->cleThrottle());
        throw ValidationException::withMessages([
            'email' => "Trop de tentatives. Réessayez dans {$secondes} seconde(s).",
        ]);
    }

    private function cleThrottle(): string
    {
        return Str::lower($this->email) . '|' . request()->ip();
    }
};
?>

<div style="min-height:70vh;display:flex;align-items:center;justify-content:center;">
    <div style="background:#fff;border:1px solid #e0dedb;border-radius:10px;padding:1.5rem;width:min(380px,92vw);box-shadow:0 10px 40px rgba(0,0,0,.08);">
        <h1 style="margin-top:0;font-size:1.25rem;text-align:center;">Trouve — Connexion</h1>

        <form wire:submit="connexion" style="display:flex;flex-direction:column;gap:.85rem;margin-top:1rem;">
            <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                Adresse e-mail
                <input type="email" wire:model="email" autofocus autocomplete="username"
                       style="padding:.5rem;border:1px solid #c0bfbc;border-radius:6px;font-size:1rem;">
                @error('email') <span style="color:#c01c28;font-size:.8rem;">{{ $message }}</span> @enderror
            </label>

            <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:#5e5c64;">
                Mot de passe
                <div x-data="{ visible: false }" style="position:relative;display:flex;">
                    <input :type="visible ? 'text' : 'password'" wire:model="password" autocomplete="current-password"
                           style="flex:1;padding:.5rem 2.4rem .5rem .5rem;border:1px solid #c0bfbc;border-radius:6px;font-size:1rem;">
                    <button type="button" @click="visible = !visible"
                            :title="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                            :aria-label="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                            style="position:absolute;right:.4rem;top:50%;transform:translateY(-50%);border:none;background:transparent;cursor:pointer;font-size:1.1rem;padding:0;line-height:1;"
                            x-text="visible ? '🙈' : '👁️'"></button>
                </div>
                @error('password') <span style="color:#c01c28;font-size:.8rem;">{{ $message }}</span> @enderror
            </label>

            <label style="display:flex;align-items:center;gap:.5rem;font-size:.9rem;color:#5e5c64;">
                <input type="checkbox" wire:model="remember">
                Se souvenir de moi
            </label>

            <button type="submit"
                    style="padding:.55rem;border:none;background:#3584e4;color:#fff;border-radius:6px;cursor:pointer;font-size:1rem;margin-top:.25rem;">Se connecter</button>
        </form>
    </div>
</div>
