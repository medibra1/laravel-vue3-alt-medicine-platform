<?php

namespace App\Domains\Patients\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;
use RuntimeException;

/**
 * Merges a batch of uploaded files (images and/or existing PDFs) into a
 * single temporary PDF — used when identity/medical documents are
 * uploaded as several photos in one go, so the patient's file keeps one
 * item per upload batch instead of one per photo.
 *
 * A file that's already a PDF has its pages appended as-is (not
 * re-rasterized) rather than flattened into an image — keeps text/quality
 * intact for any file that started as a real PDF (e.g. a scanned document
 * exported by another app).
 */
class MergeImagesIntoPdfAction
{
    /**
     * @param  UploadedFile[]  $files
     * @return string Absolute path to the merged PDF, in the system temp directory.
     */
    public function __invoke(array $files): string
    {
        if ($files === []) {
            throw new RuntimeException('Cannot merge an empty file list into a PDF.');
        }

        $merged = new Imagick;

        foreach ($files as $file) {
            $pages = new Imagick;

            try {
                $pages->readImage($file->getRealPath());
            } catch (ImagickException $e) {
                throw new RuntimeException("Could not read \"{$file->getClientOriginalName()}\" for PDF merge: {$e->getMessage()}", previous: $e);
            }

            // addImage() appends every frame currently held by its argument
            // (not just "the current cursor frame") — iterating $pages with
            // foreach and calling addImage() per iteration silently
            // duplicates frames for any multi-page source (e.g. an existing
            // PDF already in the batch). Adding the whole object once per
            // file is correct for both single-frame images and multi-page
            // PDFs alike.
            $pages->setImageFormat('pdf');
            $merged->addImage($pages);

            $pages->clear();
        }

        $merged->setImageFormat('pdf');

        $outputPath = sys_get_temp_dir().'/'.Str::uuid().'.pdf';
        $merged->writeImages($outputPath, true);
        $merged->clear();

        return $outputPath;
    }
}
