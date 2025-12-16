<?php

namespace App;

enum FileAttachmentType: string
{
    case Datasheet = 'datasheet';
    case Drawing = 'drawing';
    case Specification = 'specification';
    case Installation = 'installation';
    case Media = 'media';

    case MainImage = 'main_image';

    case GalleryImage = 'gallery_image';
}
