<?php

$files = [
    // Group
    [
        'path' => 'demo/catalog/combination-air-valves/main.jpg',
        'type' => 'image',
        'text' => 'Group Main: Combination Air Valves',
        'width' => 800,
        'height' => 600,
        'color' => [100, 149, 237], // Cornflower Blue
    ],
    [
        'path' => 'demo/catalog/combination-air-valves/gallery-1.jpg',
        'type' => 'image',
        'text' => 'Group Gallery 1',
        'width' => 800,
        'height' => 600,
        'color' => [176, 196, 222], // Light Steel Blue
    ],
    [
        'path' => 'demo/catalog/combination-air-valves/overview.pdf',
        'type' => 'pdf',
        'text' => 'Group Overview PDF',
    ],

    // Product
    [
        'path' => 'demo/products/d60s/datasheet.pdf',
        'type' => 'pdf',
        'text' => 'D60S Datasheet',
    ],
    [
        'path' => 'demo/products/d60s/main.jpg',
        'type' => 'image',
        'text' => 'Product Main: D60S',
        'width' => 800,
        'height' => 600,
        'color' => [60, 179, 113], // Medium Sea Green
    ],

    // Configuration
    [
        'path' => 'demo/configurations/d60s-p16-03-demo-a/spec-sheet.pdf',
        'type' => 'pdf',
        'text' => 'Config Spec Sheet',
    ],
    [
        'path' => 'demo/configurations/d60s-p16-03-demo-a/main.jpg',
        'type' => 'image',
        'text' => 'Config Main: Demo A',
        'width' => 800,
        'height' => 600,
        'color' => [255, 165, 0], // Orange
    ],
    [
        'path' => 'demo/configurations/d60s-p16-03-demo-a/drawing.png',
        'type' => 'image', // PNG
        'text' => 'Config Drawing: Demo A',
        'width' => 1000,
        'height' => 800,
        'color' => [255, 255, 255], // White background for drawing
        'text_color' => [0, 0, 0],
    ],

    // Part (extra inferred from history)
    [
        'path' => 'demo/parts/gallery-1.jpg',
        'type' => 'image',
        'text' => 'Part Gallery 1',
        'width' => 600,
        'height' => 600,
        'color' => [200, 200, 200],
    ],

    // Configuration Part (extra inferred)
    [
        'path' => 'demo/configuration-parts/main.jpg',
        'type' => 'image',
        'text' => 'Config Part Main',
        'width' => 600,
        'height' => 600,
        'color' => [220, 220, 220],
    ],
];

$publicPath = __DIR__ . '/../public';

foreach ($files as $file) {
    $fullPath = $publicPath . '/' . $file['path'];
    $dir = dirname($fullPath);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "Created directory: $dir\n";
    }

    if ($file['type'] === 'image') {
        $width = $file['width'];
        $height = $file['height'];
        $img = imagecreatetruecolor($width, $height);

        $bgRgb = $file['color'];
        $bg = imagecolorallocate($img, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        $textColorRgb = $file['text_color'] ?? [255, 255, 255];
        $textColor = imagecolorallocate($img, $textColorRgb[0], $textColorRgb[1], $textColorRgb[2]);

        // Center text (rough estimation)
        $fontSize = 5; // Built-in font size 1-5
        $text = $file['text'];
        $fontWidth = imagefontwidth($fontSize);
        $fontHeight = imagefontheight($fontSize);
        $textWidth = strlen($text) * $fontWidth;
        $x = ($width - $textWidth) / 2;
        $y = ($height - $fontHeight) / 2;

        imagestring($img, $fontSize, (int)$x, (int)$y, $text, $textColor);

        $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
        if (strtolower($ext) === 'png') {
            imagepng($img, $fullPath);
        } else {
            imagejpeg($img, $fullPath);
        }

        imagedestroy($img);
        echo "Created image: {$file['path']}\n";

    } elseif ($file['type'] === 'pdf') {
        // Minimal valid PDF structure
        $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n5 0 obj\n<< /Length 44 >>\nstream\nBT /F1 24 Tf 100 700 Td ({$file['text']}) Tj ET\nendstream\nendobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000060 00000 n \n0000000117 00000 n \n0000000258 00000 n \n0000000345 00000 n \ntrailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n439\n%%EOF";

        file_put_contents($fullPath, $content);
        echo "Created PDF: {$file['path']}\n";
    }
}

echo "Done.\n";
