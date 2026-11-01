<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Illuminate\Support\Js;
use Livewire\Component;

class CatalogMainImageField
{
    public static function make(): Group
    {
        return Group::make([
            Hidden::make('image_intent.operation')->default('keep')->dehydrated(false),
            Hidden::make('image_intent.source_token')->default(null)->dehydrated(false),
            Hidden::make('image_intent.draft_intent_id')->default(fn (Component $livewire): string => $livewire->catalogMainImageInitialIntent()['draft_intent_id'])->dehydrated(false),
            View::make('filament.forms.catalog-main-image')->columnSpanFull(),
            FileUpload::make('image_upload')->label('Upload an image')->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(3072)
                ->storeFiles(false)->multiple(false)->previewable()->columnSpanFull()
                ->helperText('JPEG, PNG or WebP, up to 3 MiB. Upload stays a draft until Save.')
                ->dehydrated(false),
        ])->columnSpanFull()->visible(fn (Component $livewire): bool => method_exists($livewire, 'catalogMainImageEnabled') && $livewire->catalogMainImageEnabled())
            ->extraAttributes(fn (Component $livewire): array => ['class' => 'catalog-main-image-field', 'data-catalog-image-editor' => true,
                'x-data' => 'catalogImageEditor('.Js::from($livewire->catalogMainImageEditorConfig()).')']);
    }
}
