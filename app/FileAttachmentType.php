<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum FileAttachmentType: string implements HasLabel
{
    case Datasheet = 'datasheet';
    case Drawing = 'drawing';
    case Specification = 'specification';
    case Installation = 'installation';
    case Media = 'media';

    case MainImage = 'main_image';

    case GalleryImage = 'gallery_image';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Datasheet => 'Datasheet',
            self::Drawing => 'Drawing',
            self::Specification => 'Specification',
            self::Installation => 'Installation',
            self::Media => 'Media',
            self::MainImage => 'Main Image',
            self::GalleryImage => 'Gallery Image',
        };
    }
}
