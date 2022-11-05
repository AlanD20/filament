<?php

namespace Filament\Tests\App\Fixtures\Resources;

use Filament\Resources\Resource;
use Filament\Tests\App\Fixtures\Resources\PostCategoryResource\Pages;
use Filament\Tests\Models\PostCategory;

class PostCategoryResource extends Resource
{
    protected static ?string $model = PostCategory::class;

    protected static ?string $navigationGroup = 'Blog';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostCategories::route('/'),
            'create' => Pages\CreatePostCategory::route('/create'),
            'view' => Pages\ViewPostCategory::route('/{record}'),
            'edit' => Pages\EditPostCategory::route('/{record}/edit'),
        ];
    }
}