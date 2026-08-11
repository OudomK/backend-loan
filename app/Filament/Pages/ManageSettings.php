<?php

namespace App\Filament\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use App\Models\Setting;
use App\Support\AdminFontRegistry;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;



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

    private function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }

        return $default;
    }

    public function mount(): void
    {
        $dbSettings = Setting::pluck('value', 'key')->toArray();
        $frontendFont = strtolower((string) ($dbSettings['frontend_font_family'] ?? 'battambang'));
        if (!array_key_exists($frontendFont, AdminFontRegistry::options())) {
            $frontendFont = 'battambang';
        }

        $this->form->fill([
            'company_name' => $dbSettings['company_name'] ?? config('app.company_name'),
            'company_logo' => $dbSettings['company_logo'] ?? '',
            'default_language' => $dbSettings['default_language'] ?? 'EN',
            'admin_font_family' => AdminFontRegistry::resolveKey(isset($dbSettings['admin_font_family']) ? (string) $dbSettings['admin_font_family'] : null),
            'available_fonts_count' => (string) AdminFontRegistry::count(),
            'frontend_font_family' => $frontendFont,
            'pdf_export_font' => strtolower((string) ($dbSettings['pdf_export_font'] ?? $frontendFont)),
            'print_schedule_font' => strtolower((string) ($dbSettings['print_schedule_font'] ?? ($dbSettings['pdf_export_font'] ?? $frontendFont))),
            'copyright_text' => $dbSettings['copyright_text'] ?? '© ' . date('Y') . ' ' . config('app.company_name'),
            'exchange_rate_khr_to_usd' => $dbSettings['exchange_rate_khr_to_usd'] ?? 4000,
            'default_interest_rate' => $dbSettings['default_interest_rate'] ?? 1.5,
            'default_penalty_usd' => $dbSettings['default_penalty_usd'] ?? 2.5,
            'default_penalty_khr' => $dbSettings['default_penalty_khr'] ?? 10000,
            'prepayment_days' => $dbSettings['prepayment_days'] ?? 3,
            'chart_max_amount' => $dbSettings['chart_max_amount'] ?? '',
            'enable_dividend_tax' => $this->toBool($dbSettings['enable_dividend_tax'] ?? false, false),
            'auto_dividend_tax' => $this->toBool($dbSettings['auto_dividend_tax'] ?? false, false),
            'dividend_tax_rate' => $dbSettings['dividend_tax_rate'] ?? 0,
            'me_name' => Auth::user()->name,
            'me_email' => Auth::user()->email,
            'me_avatar_url' => Auth::user()->avatar_url,
            'default_payment_qr_id' => $dbSettings['default_payment_qr_id'] ?? null,
            'co_phone_display_mode' => $dbSettings['co_phone_display_mode'] ?? 'one_line',
            'co_phone_display_count' => $dbSettings['co_phone_display_count'] ?? '3',
            'excel_export_font' => $dbSettings['excel_export_font'] ?? 'Khmer OS Siemreap',
            'require_loan_purpose' => $this->toBool($dbSettings['require_loan_purpose'] ?? true, true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Me ──────────────────────────────────────────────────────
                // ── Me ──────────────────────────────────────────────────────
                Section::make('My Account')
                    ->hidden(fn() => $this->activeTab !== 'me')
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
                            ->dehydrated(fn($state) => filled($state)),
                        TextInput::make('me_password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->dehydrated(false)
                            ->maxLength(255),
                    ])->columns(2),

                // ── Company Profile ─────────────────────────────────────────
                // ── Company Profile ─────────────────────────────────────────
                Section::make('Company Profile')
                    ->hidden(fn() => $this->activeTab !== 'profile')
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
                // ── Exchange Rate ────────────────────────────────────────────
                Section::make('Exchange Rate')
                    ->hidden(fn() => $this->activeTab !== 'exchange_rate')
                    ->schema([
                        TextInput::make('exchange_rate_khr_to_usd')
                            ->label('Exchange Rate (1 USD to KHR)')
                            ->helperText('Used for dashboard totals (e.g. 4000)')
                            ->numeric()
                            ->default(4000)
                            ->required(),
                    ]),

                // ── Font Settings ───────────────────────────────────────
                // ── Font Settings ───────────────────────────────────────
                Section::make('Font Settings')
                    ->hidden(fn() => $this->activeTab !== 'font')
                    ->schema([
                        FileUpload::make('import_font_file')
                            ->label('Import New Font File (.ttf, .otf)')
                            ->disk('public')
                            ->directory('custom-fonts')
                            ->acceptedFileTypes(['font/ttf', 'font/otf', 'application/x-font-truetype', 'application/x-font-opentype', 'font/sfnt'])
                            ->dehydrated(false)
                            ->live()
                            ->helperText('Upload a .ttf or .otf file from your computer to import it instantly into the system as a custom font.')
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (empty($state))
                                    return;

                                try {
                                    $filePath = $state;
                                    $basename = pathinfo($filePath, PATHINFO_FILENAME);

                                    // Make name pretty, e.g. "KANTUMRUYPRO-BOLD" -> "Kantumruypro Bold"
                                    $cleanName = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $basename);
                                    $cleanName = ucwords(trim(preg_replace('/\s+/', ' ', strtolower($cleanName))));

                                    // Key must be unique slug
                                    $key = strtolower(str_replace(' ', '_', $cleanName));

                                    \App\Models\CustomFont::updateOrCreate(
                                        ['key' => $key],
                                        [
                                            'name' => $cleanName,
                                            'file_path' => $filePath,
                                            'is_system' => false,
                                            'is_active' => true,
                                        ]
                                    );

                                    $set('import_font_file', null);

                                    Notification::make()
                                        ->title("Font '{$cleanName}' imported successfully!")
                                        ->success()
                                        ->send();

                                    $set('available_fonts_count', (string) AdminFontRegistry::count());

                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('Failed to import font')
                                        ->danger()
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }),

                        TextEntry::make('manage_fonts')
                            ->label('Manage Imported Fonts')
                            ->state(new \Illuminate\Support\HtmlString('
                                <a href="' . \App\Filament\Resources\CustomFonts\CustomFontResource::getUrl() . '" 
                                   class="text-primary-600 font-bold underline hover:text-primary-500">
                                    Click here to edit, remove, or deactivate imported fonts
                                </a>
                            '))
                            ->html(),

                        TextInput::make('available_fonts_count')
                            ->label('Total Available Fonts')
                            ->default((string) AdminFontRegistry::count())
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('admin_font_family')
                            ->label('Admin Panel Font')
                            ->options(fn() => AdminFontRegistry::options())
                            ->default(AdminFontRegistry::defaultKey())
                            ->required()
                            ->native(false)
                            ->helperText('System fonts: ' . AdminFontRegistry::coreCount() . '. Imported active fonts: ' . AdminFontRegistry::activeCustomCount() . '.'),
                        Select::make('frontend_font_family')
                            ->label('QuickFund App Font')
                            ->options(fn() => AdminFontRegistry::options())
                            ->default('battambang')
                            ->required()
                            ->native(false)
                            ->helperText('Used by Flutter frontend app. The app auto-syncs this setting about every 5 seconds.'),
                        Select::make('pdf_export_font')
                            ->label('PDF Export Font')
                            ->options(fn() => AdminFontRegistry::options())
                            ->default('noto_sans_khmer')
                            ->required()
                            ->native(false)
                            ->helperText('Used by all PDF exports generated from the Flutter frontend app.'),
                        Select::make('print_schedule_font')
                            ->label('Print Schedule Font')
                            ->options(fn() => AdminFontRegistry::options())
                            ->default('noto_sans_khmer')
                            ->required()
                            ->native(false)
                            ->helperText('Used by repayment schedule print preview and printed schedule output.'),
                        Select::make('excel_export_font')
                            ->label('Excel Export Font')
                            ->options(fn() => array_combine(array_values(AdminFontRegistry::options()), array_values(AdminFontRegistry::options())))
                            ->default('Khmer OS Siemreap')
                            ->required()
                            ->native(false)
                            ->helperText('Font family used for all exported Excel reports (e.g. Khmer OS Siemreap).'),
                    ]),

                Section::make('Loan Configuration')
                    ->hidden(fn() => $this->activeTab !== 'loan_config')
                    ->schema([
                        Toggle::make('require_loan_purpose')
                            ->label('Require Loan Purpose')
                            ->helperText('If ON, Loan Purpose is required in Loan Application. If OFF, it can be skipped.')
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
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
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->step('100'),
                        TextInput::make('prepayment_days')
                            ->label('Prepayment Days Show (Days)')
                            ->helperText('How many days in advance to show upcoming payments in the Prepayment table (e.g., 3).')
                            ->numeric()
                            ->minValue(1)
                            ->default(3),
                        TextInput::make('chart_max_amount')
                            ->label('Chart Max Amount ($)')
                            ->helperText('Maximum Y-axis value for the Productivity bar chart on the Dashboard. Leave empty for auto-scale.')
                            ->numeric()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->step('1000')
                            ->minValue(0)
                            ->placeholder('Auto'),
                    ])->columns(2),

                Section::make('Dividend Configuration')
                    ->hidden(fn() => $this->activeTab !== 'dividend_config')
                    ->schema([
                        Toggle::make('enable_dividend_tax')
                            ->label('Enable Dividend Tax')
                            ->helperText('OFF: hide/ignore tax in Dividend flow. ON: allow tax input and net calculation.')
                            ->default(false)
                            ->inline(false),
                        Toggle::make('auto_dividend_tax')
                            ->label('Auto Deduct Dividend Tax')
                            ->helperText('OFF: user can type tax amount manually. ON: system auto-calculates tax from Tax Rate (%).')
                            ->default(false)
                            ->inline(false),
                        TextInput::make('dividend_tax_rate')
                            ->label('Dividend Tax Rate (%)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->helperText('Used only when Auto Deduct Dividend Tax is ON. Example: 10 means 10% tax.'),
                    ]),

                Section::make('Payment QR Codes')
                    ->hidden(fn() => $this->activeTab !== 'payment_qr')
                    ->schema([
                        Select::make('default_payment_qr_id')
                            ->label('Default QR Code')
                            ->options(\App\Models\PaymentQr::pluck('name', 'id'))
                            ->placeholder('Select a default QR')
                            ->helperText('This QR will be selected by default in new loan applications.'),

                        TextEntry::make('manage_qrs')
                            ->label('Full Management')
                            ->state(new \Illuminate\Support\HtmlString('
                                <a href="' . \App\Filament\Resources\PaymentQrs\PaymentQrResource::getUrl() . '" 
                                   class="text-primary-600 font-bold underline hover:text-primary-500">
                                    Click here to manage, upload, or delete all QR Codes
                                </a>
                            '))
                            ->html(),
                        Select::make('co_phone_display_mode')
                            ->label('CO Phone Display')
                            ->options([
                                'one_line' => '1 line',
                                'two_lines' => '2 lines',
                            ])
                            ->default('one_line')
                            ->native(false)
                            ->helperText('Controls how CO phone numbers appear on the repayment schedule print screen.'),
                        Select::make('co_phone_display_count')
                            ->label('CO Phone Count')
                            ->options([
                                '1' => '1 number',
                                '2' => '2 numbers',
                                '3' => '3 numbers',
                            ])
                            ->default('3')
                            ->native(false)
                            ->helperText('Choose how many CO phone numbers to show on the repayment schedule print screen.'),
                    ]),
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
