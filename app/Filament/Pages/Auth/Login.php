<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('Username or Email'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login_type = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $login_type => $data['login'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    /**
     * Override authenticate to bind the new session ID to the user,
     * enforcing single-session-per-account on the Admin Panel.
     */
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        // parent::authenticate() returns null when MFA or rate-limit kicks in
        if ($response === null) {
            return null;
        }

        // Store the freshly regenerated session ID on the user record
        /** @var \App\Models\User|null $user */
        $user = Filament::auth()->user();
        if ($user) {
            $user->forceFill([
                'current_session_id' => session()->getId(),
            ])->saveQuietly();
        }

        return $response;
    }
}
