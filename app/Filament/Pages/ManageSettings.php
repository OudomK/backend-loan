<?php

namespace App\Filament\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\Setting;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return 'General Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 16;
    }

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Information')
                    ->description('Manage your basic company details here.')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('company_email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),
                        Textarea::make('company_address')
                            ->label('Address')
                            ->rows(3)
                            ->maxLength(65535),
                    ])->columns(2),

                Section::make('Loan Configuration')
                    ->description('Default settings for new loans.')
                    ->schema([
                        TextInput::make('default_interest_rate')
                            ->label('Default Interest Rate (%)')
                            ->numeric()
                            ->step('0.01'),
                        TextInput::make('default_penalty_rate')
                            ->label('Default Penalty Rate (%)')
                            ->numeric()
                            ->step('0.01'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
