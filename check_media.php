<?php
use Spatie\MediaLibrary\MediaCollections\Models\Media;

$m = Media::where('file_name', '01KCMC0B77T49S7WTGMV3TJ8RD.jpg')->first();
if ($m) {
    echo 'Disk: ' . $m->disk . PHP_EOL;
    echo 'Path: ' . $m->getPath() . PHP_EOL;
} else {
    echo "Media not found.\n";
}
