<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity as ActivityModel;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextEntry::make('description'),
                        TextEntry::make('log_name'),
                        TextEntry::make('created_at')
                            ->label('Logged At')
                            ->dateTime(),
                        TextEntry::make('properties.ip')
                            ->label('IP Address')
                            ->url(fn($state) => $state ? "https://ipinfo.io/{$state}" : null)
                            ->openUrlInNewTab()
                            ->color('primary'),
                    ])->columns(4),

                Section::make('Subject & Causer')
                    ->schema([
                        TextEntry::make('subject_type')
                            ->label('Subject Model')
                            ->formatStateUsing(fn($state) => $state ? class_basename($state) : '-'),
                        TextEntry::make('subject_id')
                            ->label('Subject ID'),
                        TextEntry::make('causer.name')
                            ->label('User Name'),
                        TextEntry::make('causer.roles.name')
                            ->label('User Role')
                            ->badge()
                            ->color('info'),
                    ])->columns(2),

                Section::make('Properties')
                    ->description('Each row shows the value before the edit and the value after the edit.')
                    ->schema([
                        TextEntry::make('property_changes')
                            ->label('')
                            ->state(fn(?ActivityModel $record): HtmlString => static::renderPropertyChanges($record))
                            ->html(),
                    ]),
            ]);
    }

    /**
     * @return array<int, array{field: string, before: string|null, after: string|null}>
     */
    protected static function getPropertyChanges(?ActivityModel $record): array
    {
        $properties = $record?->properties?->toArray() ?? [];
        $newValues = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
        $oldValues = is_array($properties['old'] ?? null) ? $properties['old'] : [];

        $fields = array_values(array_unique([
            ...array_keys($oldValues),
            ...array_keys($newValues),
        ]));

        return array_map(
            fn(string $field): array => [
                'field' => Str::headline($field),
                'before' => static::formatPropertyValue($oldValues[$field] ?? null),
                'after' => static::formatPropertyValue($newValues[$field] ?? null),
            ],
            $fields,
        );
    }

    protected static function formatPropertyValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (
            is_string($value) &&
            preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $value, $matches)
        ) {
            return str_replace('T', ' ', $matches[0]);
        }

        return (string) $value;
    }

    protected static function renderPropertyChanges(?ActivityModel $record): HtmlString
    {
        $changes = static::getPropertyChanges($record);

        if ($changes === []) {
            return new HtmlString('<p>No field-level changes were captured for this activity.</p>');
        }

        $rows = array_map(
            fn(array $change): string => sprintf(
                '<tr>' .
                    '<td><strong>%s:</strong></td>' .
                    '<td><code>%s</code></td>' .
                    '<td align="center">-&gt;</td>' .
                    '<td><code>%s</code></td>' .
                '</tr>',
                e($change['field']),
                e($change['before'] ?? '-'),
                e($change['after'] ?? '-'),
            ),
            $changes,
        );

        return new HtmlString(
            '<table width="100%" cellpadding="6" cellspacing="0">' .
                '<thead>' .
                    '<tr>' .
                        '<th align="left"></th>' .
                        '<th align="left">Before</th>' .
                        '<th></th>' .
                        '<th align="left">After</th>' .
                    '</tr>' .
                '</thead>' .
                '<tbody>' .
                    implode('', $rows) .
                '</tbody>' .
            '</table>'
        );
    }
}
