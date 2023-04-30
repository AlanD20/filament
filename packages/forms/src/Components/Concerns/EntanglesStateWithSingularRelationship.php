<?php

namespace Filament\Forms\Components\Concerns;

use Closure;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Contracts\CanEntangleWithSingularRelationships;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait EntanglesStateWithSingularRelationship
{
    protected Model | bool | null $cachedExistingRecord = false;

    protected string | null $relationship = null;

    protected ?Closure $mutateRelationshipDataBeforeCreateUsing = null;

    protected ?Closure $mutateRelationshipDataBeforeFillUsing = null;

    protected ?Closure $mutateRelationshipDataBeforeSaveUsing = null;

    public function relationship(string $relationshipName): static
    {
        $this->relationship = $relationshipName;
        $this->statePath($relationshipName);

        $this->loadStateFromRelationshipsUsing(static function (Component | CanEntangleWithSingularRelationships $component) {
            $component->clearCachedExistingRecord();

            $component->fillFromRelationship();
        });

        $this->saveRelationshipsBeforeChildrenUsing(static function (Component | CanEntangleWithSingularRelationships $component, HasForms $livewire): void {
            $relationship = $component->getRelationship();

            if ($relationship instanceof BelongsTo) {
                return;
            }

            $record = $component->getCachedExistingRecord();

            if ($record) {
                return;
            }

            $data = $component->getChildComponentContainer()->getState(shouldCallHooksBefore: false);
            $data = $component->mutateRelationshipDataBeforeCreate($data);

            $relatedModel = $component->getRelatedModel();

            $translatableContentDriver = $livewire->makeFormTranslatableContentDriver();

            if ($translatableContentDriver) {
                $record = $translatableContentDriver->makeRecord($relatedModel, $data);
            } else {
                $record = new $relatedModel();
                $record->fill($data);
            }

            $relationship->save($record);
        });

        $this->saveRelationshipsUsing(static function (Component | CanEntangleWithSingularRelationships $component, HasForms $livewire): void {
            $data = $component->getChildComponentContainer()->getState(shouldCallHooksBefore: false);

            $record = $component->getCachedExistingRecord();

            $translatableContentDriver = $livewire->makeFormTranslatableContentDriver();

            if ($record) {
                $data = $component->mutateRelationshipDataBeforeSave($data);

                $translatableContentDriver ?
                    $translatableContentDriver->updateRecord($record, $data) :
                    $record->fill($data)->save();

                return;
            }

            $relationship = $component->getRelationship();

            if (! ($relationship instanceof BelongsTo)) {
                return;
            }

            $data = $component->mutateRelationshipDataBeforeCreate($data);

            $relatedModel = $component->getRelatedModel();

            if ($translatableContentDriver) {
                $record = $translatableContentDriver->makeRecord($relatedModel, $data);
            } else {
                $record = new $relatedModel();
                $record->fill($data);
            }

            $record->save();

            $relationship->associate($record);
            $relationship->getParent()->save();
        });

        $this->dehydrated(false);

        return $this;
    }

    public function fillFromRelationship(): void
    {
        $record = $this->getCachedExistingRecord();

        if (! $record) {
            $this->getChildComponentContainer()->fill();

            return;
        }

        $data = $this->mutateRelationshipDataBeforeFill(
            $this->getStateFromRelatedRecord($record),
        );

        $this->getChildComponentContainer()->fill($data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getStateFromRelatedRecord(Model $record): array
    {
        if ($translatableContentDriver = $this->getLivewire()->makeFormTranslatableContentDriver()) {
            return $translatableContentDriver->getRecordAttributesToArray($record);
        }

        return $record->attributesToArray();
    }

    /**
     * @param  array-key  $key
     */
    public function getChildComponentContainer($key = null): ComponentContainer
    {
        $container = parent::getChildComponentContainer($key);

        $relationship = $this->getRelationship();

        if (! $relationship) {
            return $container;
        }

        return $container->model($this->getCachedExistingRecord() ?? $this->getRelatedModel());
    }

    public function getRelationship(): BelongsTo | HasOne | MorphOne | null
    {
        $name = $this->getRelationshipName();

        if (blank($name)) {
            return null;
        }

        return $this->getModelInstance()->{$name}();
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationship;
    }

    public function getRelatedModel(): ?string
    {
        return $this->getRelationship()?->getModel()::class;
    }

    public function getCachedExistingRecord(): ?Model
    {
        if ($this->cachedExistingRecord !== false) {
            return $this->cachedExistingRecord;
        }

        $record = $this->getRelationship()?->getResults();

        return $this->cachedExistingRecord = $record?->exists ? $record : null;
    }

    public function clearCachedExistingRecord(): void
    {
        $this->cachedExistingRecord = false;
    }

    public function mutateRelationshipDataBeforeCreateUsing(?Closure $callback): static
    {
        $this->mutateRelationshipDataBeforeCreateUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateRelationshipDataBeforeCreate(array $data): array
    {
        if ($this->mutateRelationshipDataBeforeCreateUsing instanceof Closure) {
            $data = $this->evaluate($this->mutateRelationshipDataBeforeCreateUsing, [
                'data' => $data,
            ]);
        }

        return $data;
    }

    public function mutateRelationshipDataBeforeSaveUsing(?Closure $callback): static
    {
        $this->mutateRelationshipDataBeforeSaveUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateRelationshipDataBeforeFill(array $data): array
    {
        if ($this->mutateRelationshipDataBeforeFillUsing instanceof Closure) {
            $data = $this->evaluate($this->mutateRelationshipDataBeforeFillUsing, [
                'data' => $data,
            ]);
        }

        return $data;
    }

    public function mutateRelationshipDataBeforeFillUsing(?Closure $callback): static
    {
        $this->mutateRelationshipDataBeforeFillUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateRelationshipDataBeforeSave(array $data): array
    {
        if ($this->mutateRelationshipDataBeforeSaveUsing instanceof Closure) {
            $data = $this->evaluate($this->mutateRelationshipDataBeforeSaveUsing, [
                'data' => $data,
            ]);
        }

        return $data;
    }
}
