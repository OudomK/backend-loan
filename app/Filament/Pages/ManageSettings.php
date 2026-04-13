<?php

namespace App\Filament\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use App\Models\Setting;
use App\Support\AdminFontRegistry;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * @var array<string, string>
     */
    private const FRONTEND_FONT_OPTIONS = [
        'battambang' => 'Battambang',
        'kantumruy_pro' => 'Kantumruy Pro',
        'krasar' => 'Krasar',
        'moul' => 'Moul',
        'noto_sans_khmer' => 'Noto Sans Khmer',
    ];

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 16;
    }

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public string $activeTab = 'me';

    public function mount(): void
    {
        $dbSettings = Setting::pluck('value', 'key')->toArray();
        $frontendFont = strtolower((string) ($dbSettings['frontend_font_family'] ?? 'battambang'));
        if (! array_key_exists($frontendFont, self::FRONTEND_FONT_OPTIONS)) {
            $frontendFont = 'battambang';
        }
        
        $this->form->fill([
            'company_name' => $dbSettings['company_name'] ?? config('app.company_name'),
            'company_logo' => $dbSettings['company_logo'] ?? '',
            'default_language' => $dbSettings['default_language'] ?? 'EN',
            'admin_font_family' => AdminFontRegistry::resolveKey(isset($dbSettings['admin_font_family']) ? (string) $dbSettings['admin_font_family'] : null),
            'available_fonts_count' => (string) AdminFontRegistry::count(),
            'frontend_font_family' => $frontendFont,
            'copyright_text' => $dbSettings['copyright_text'] ?? '© ' . date('Y') . ' ' . config('app.company_name'),
            'exchange_rate_khr_to_usd' => $dbSettings['exchange_rate_khr_to_usd'] ?? 4000,
            'default_interest_rate' => $dbSettings['default_interest_rate'] ?? 1.5,
            'default_penalty_usd' => $dbSettings['default_penalty_usd'] ?? 2.5,
            'default_penalty_khr' => $dbSettings['default_penalty_khr'] ?? 10000,
            'me_name' => Auth::user()->name,
            'me_email' => Auth::user()->email,
            'me_avatar_url' => Auth::user()->avatar_url,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Me ──────────────────────────────────────────────────────
                Section::make('My Account')
                    ->hidden(fn () => $this->activeTab !== 'me')
                    ->schema([
                        FileUpload::make('me_avatar_url')
                            ->label('Profile Picture')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                /** @var \App\Models\User $user */
                                $user = Auth::user();
                                $user->avatar_url = $state;
                                $user->save();

                                Notification::make()
                                    ->title('Profile picture updated')
                                    ->success()
                                    ->send();
                            }),
                        TextInput::make('me_name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('me_email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email', ignorable: Auth::user())
                            ->maxLength(255),
                        TextInput::make('me_password')
                            ->label('New Password')
                            ->password()
                            ->maxLength(255)
                            ->confirmed()
                            ->dehydrated(fn ($state) => filled($state)),
                        TextInput::make('me_password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->dehydrated(false)
                            ->maxLength(255),
                    ])->columns(2),

                // ── Company Profile ─────────────────────────────────────────
                Section::make('Company Profile')
                    ->hidden(fn () => $this->activeTab !== 'profile')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->required()
                            ->default(config('app.company_name'))
                            ->columnSpan(2),
                        FileUpload::make('company_logo')
                            ->label('Company Logo')
                            ->image()
                            ->disk('public')
                            ->directory('settings')
                            ->visibility('public')
                            ->columnSpan(1)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                Setting::updateOrCreate(
                                    ['key' => 'company_logo'],
                                    ['value' => $state]
                                );

                                Notification::make()
                                    ->title('Company logo updated')
                                    ->success()
                                    ->send();
                            }),
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

                // ── Exchange Rate ────────────────────────────────────────────
                Section::make('Exchange Rate')
                    ->hidden(fn () => $this->activeTab !== 'exchange_rate')
                    ->schema([
                        TextInput::make('exchange_rate_khr_to_usd')
                            ->label('Exchange Rate (1 USD to KHR)')
                            ->helperText('Used for dashboard totals (e.g. 4000)')
                            ->numeric()
                            ->default(4000)
                            ->required(),
                    ]),

                // ── Loan Configuration ───────────────────────────────────────
                Section::make('Font Settings')
                    ->hidden(fn () => $this->activeTab !== 'font')
                    ->schema([
                        TextInput::make('available_fonts_count')
                            ->label('Total Available Fonts')
                            ->default((string) AdminFontRegistry::count())
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('admin_font_family')
                            ->label('Admin Panel Font')
                            ->options(AdminFontRegistry::options())
                            ->default(AdminFontRegistry::defaultKey())
                            ->required()
                            ->native(false)
                            ->helperText('Available fonts: ' . AdminFontRegistry::count() . ' (' . AdminFontRegistry::labelsAsText() . ')'),
                        Select::make('frontend_font_family')
                            ->label('Frontend App Font')
                            ->options(self::FRONTEND_FONT_OPTIONS)
                            ->default('battambang')
                            ->required()
                            ->native(false)
                            ->helperText('Used by Flutter frontend app. The app auto-syncs this setting about every 5 seconds.'),
                    ]),

                Section::make('Loan Configuration')
                    ->hidden(fn () => $this->activeTab !== 'loan_config')
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
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // 1. Save Personal Profile (Me)
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (isset($data['me_name'])) {
            $user->name = $data['me_name'];
        }
        if (isset($data['me_email'])) {
            $user->email = $data['me_email'];
        }
        if (!empty($data['me_password'])) {
            $user->password = Hash::make($data['me_password']);
        }
        if (array_key_exists('me_avatar_url', $data)) {
            $user->avatar_url = $data['me_avatar_url'];
        }
        $user->save();

        // Remove personal fields from data so they don't corrupt the glonal settings table
        unset($data['me_name'], $data['me_email'], $data['me_password'], $data['me_password_confirmation'], $data['me_avatar_url']);

        // 2. Save Global Settings
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

        $this->redirect('/admin');
    }
}
