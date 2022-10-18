<?php

namespace Filament\Actions;

use Filament\Actions\Concerns\CanCustomizeProcess;
use Illuminate\Database\Eloquent\Model;

class RestoreAction extends Action
{
    use CanCustomizeProcess;

    public static function getDefaultName(): ?string
    {
        return 'restore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-support::actions/restore.single.label'));

        $this->modalHeading(fn (): string => __('filament-support::actions/restore.single.modal.heading', ['label' => $this->getRecordTitle()]));

        $this->modalButton(__('filament-support::actions/restore.single.modal.actions.restore.label'));

        $this->successNotificationTitle(__('filament-support::actions/restore.single.messages.restored'));

        $this->color('secondary');

        $this->groupedIcon('heroicon-m-arrow-uturn-left');

        $this->requiresConfirmation();

        $this->action(function (Model $record): void {
            if (! method_exists($record, 'restore')) {
                $this->failure();

                return;
            }

            $this->process(static fn () => $record->restore());

            $this->success();
        });

        $this->visible(static function (Model $record): bool {
            if (! method_exists($record, 'trashed')) {
                return false;
            }

            return $record->trashed();
        });
    }
}