<?php

namespace App\Filament\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
        $dbSettings = Setting::pluck('value', 'key')->toArray();
        
        $this->form->fill([
            'company_name' => $dbSettings['company_name'] ?? config('app.company_name'),
            'company_logo' => $dbSettings['company_logo'] ?? '',
            'default_language' => $dbSettings['default_language'] ?? 'EN',
            'copyright_text' => $dbSettings['copyright_text'] ?? '© ' . date('Y') . ' ' . config('app.company_name'),
            'exchange_rate_khr_to_usd' => $dbSettings['exchange_rate_khr_to_usd'] ?? 4000,
            'default_interest_rate' => $dbSettings['default_interest_rate'] ?? 1.5,
            'default_penalty_usd' => $dbSettings['default_penalty_usd'] ?? 2.5,
            'default_penalty_khr' => $dbSettings['default_penalty_khr'] ?? 10000,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make('Profile')
                            ->icon('heroicon-m-building-office')
                            ->schema([
                                TextInput::make('company_name')
                                    ->label('Company Name')
                                    ->required()
                                    ->default(config('app.company_name'))
                                    ->columnSpan(2),
                                FileUpload::make('company_logo')
                                    ->label('Company Logo')
                                    ->image()
                                    ->directory('settings')
                                    ->visibility('public')
                                    ->columnSpan(1),
                                Select::make('default_language')
                                    ->label('System Language')
                                    ->options([
                                        'EN' => 'English',
                                        'KH' => 'Khmer',
                                    ])
                                    ->default('EN')
                                    ->columnSpan(1),
                                TextInput::make('copyright_text')
                                    ->label('Copyright Text')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ])->columns(2),

                        Tab::make('Exchange Rate')
                            ->icon('heroicon-m-currency-dollar')
                            ->schema([
                                TextInput::make('exchange_rate_khr_to_usd')
                                    ->label('Exchange Rate (1 USD to KHR)')
                                    ->helperText('Used for dashboard totals (e.g. 4000)')
                                    ->numeric()
                                    ->default(4000)
                                    ->required(),
                            ]),

                        Tab::make('Loan Configuration')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                TextInput::make('default_interest_rate')
                                    ->label('Default Interest Rate (%)')
                                    ->numeric()
                                    ->step('0.01'),
                                TextInput::make('default_penalty_usd')
                                    ->label('Default Penalty (USD/Day)')
                                    ->numeric()
                                    ->step('0.01'),
                                TextInput::make('default_penalty_khr')
                                    ->label('Default Penalty (KHR/Day)')
                                    ->numeric()
                                    ->step('100'),
                            ])->columns(2),
                    ])->columnSpanFull(),
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
