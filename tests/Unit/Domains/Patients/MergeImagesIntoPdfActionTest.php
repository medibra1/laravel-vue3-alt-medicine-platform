<?php

use App\Domains\Patients\Services\MergeImagesIntoPdfAction;
use Illuminate\Http\UploadedFile;
use Imagick;

function makeTestImage(string $path, string $format, string $color = 'red'): void
{
    $image = new Imagick;
    $image->newImage(100, 100, $color);
    $image->setImageFormat($format);
    $image->writeImage($path);
    $image->clear();
}

test('merges two images into a single multi-page pdf', function () {
    $path1 = sys_get_temp_dir().'/'.uniqid('merge_test_').'.png';
    $path2 = sys_get_temp_dir().'/'.uniqid('merge_test_').'.png';
    makeTestImage($path1, 'png', 'red');
    makeTestImage($path2, 'png', 'blue');

    $files = [
        new UploadedFile($path1, 'first.png', 'image/png', null, true),
        new UploadedFile($path2, 'second.png', 'image/png', null, true),
    ];

    $mergedPath = (new MergeImagesIntoPdfAction)($files);

    expect($mergedPath)->toBeFile();

    $pdf = new Imagick;
    $pdf->readImage($mergedPath);
    expect($pdf->getNumberImages())->toBe(2);

    @unlink($path1);
    @unlink($path2);
    @unlink($mergedPath);
});

test('appends every page of an existing pdf already in the batch, not just one', function () {
    $imagePath = sys_get_temp_dir().'/'.uniqid('merge_test_').'.png';
    makeTestImage($imagePath, 'png', 'green');

    $existingPdfPath = sys_get_temp_dir().'/'.uniqid('merge_test_').'.pdf';
    $twoPagePdf = new Imagick;
    $page1 = new Imagick;
    $page1->newImage(100, 100, 'yellow');
    $page1->setImageFormat('pdf');
    $page2 = new Imagick;
    $page2->newImage(100, 100, 'purple');
    $page2->setImageFormat('pdf');
    $twoPagePdf->addImage($page1);
    $twoPagePdf->addImage($page2);
    $twoPagePdf->setImageFormat('pdf');
    $twoPagePdf->writeImages($existingPdfPath, true);
    $twoPagePdf->clear();

    $files = [
        new UploadedFile($imagePath, 'photo.png', 'image/png', null, true),
        new UploadedFile($existingPdfPath, 'existing.pdf', 'application/pdf', null, true),
    ];

    $mergedPath = (new MergeImagesIntoPdfAction)($files);

    $pdf = new Imagick;
    $pdf->readImage($mergedPath);
    expect($pdf->getNumberImages())->toBe(3);

    @unlink($imagePath);
    @unlink($existingPdfPath);
    @unlink($mergedPath);
});
