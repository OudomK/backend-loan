<?php

namespace App\Filament\Resources\Dividends\Pages;

use App\Filament\Resources\Dividends\DividendResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDividend extends EditRecord
{
    protected static string $resource = DividendResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->isLocked()) {
            Notification::make()
                ->title('មិនអាចកែប្រែបានទេ')
                ->body('ភាគលាភនេះត្រូវបានបង់រួចហើយ មិនអាចកែប្រែទិន្នន័យហិរញ្ញវត្ថុបានទេ។')
                ->danger()
                ->send();

            $this->redirect(DividendResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => !$this->record->isLocked()),
            ForceDeleteAction::make()
                ->visible(fn () => !$this->record->isLocked()),
            RestoreAction::make(),
        ];
    }
}
