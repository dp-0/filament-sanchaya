<?php

namespace DP0\Sanchaya\Database\Seeders;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SanchayaSeeder extends Seeder
{
    public function run(): void
    {
        $disk = config('filament-sanchaya.default_disk', 'public');
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        // 1. Documents Folder
        $docsFolder = $fileModel::firstOrCreate(
            [
                'parent_id' => null,
                'disk' => $disk,
                'type' => 'folder',
                'file_name' => 'documents',
            ],
            [
                'original_name' => 'Documents',
                'path' => 'documents',
                'size' => 0,
            ]
        );

        // 2. Invoices Subfolder
        $invoicesFolder = $fileModel::firstOrCreate(
            [
                'parent_id' => $docsFolder->id,
                'disk' => $disk,
                'type' => 'folder',
                'file_name' => 'invoices',
            ],
            [
                'original_name' => 'Invoices',
                'path' => 'documents/invoices',
                'size' => 0,
            ]
        );

        // 3. Images Folder
        $imagesFolder = $fileModel::firstOrCreate(
            [
                'parent_id' => null,
                'disk' => $disk,
                'type' => 'folder',
                'file_name' => 'images',
            ],
            [
                'original_name' => 'Images',
                'path' => 'images',
                'size' => 0,
            ]
        );

        // Sample dummy files to generate
        $samples = [
            [
                'parent_id' => $docsFolder->id,
                'path' => 'documents/quarterly-report.pdf',
                'file_name' => 'quarterly-report.pdf',
                'original_name' => 'Quarterly Financial Report.pdf',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 245760,
                'content' => '%PDF-1.4 Dummy PDF content for preview testing',
                'metadata' => ['title' => 'Q3 Financials', 'description' => 'Prepared by Accounting'],
            ],
            [
                'parent_id' => $invoicesFolder->id,
                'path' => 'documents/invoices/inv-2026-001.pdf',
                'file_name' => 'inv-2026-001.pdf',
                'original_name' => 'Invoice #2026-001.pdf',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 102400,
                'content' => '%PDF-1.4 Dummy invoice',
                'metadata' => ['title' => 'Client Invoice #2026-001'],
            ],
            [
                'parent_id' => $imagesFolder->id,
                'path' => 'images/hero-banner.jpg',
                'file_name' => 'hero-banner.jpg',
                'original_name' => 'Hero Banner.jpg',
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
                'size' => 524288,
                'content' => 'dummy jpeg bytes',
                'metadata' => ['alt_text' => 'Website Hero Banner', 'title' => 'Homepage Banner'],
            ],
            [
                'parent_id' => null,
                'path' => 'sanchaya/welcome.txt',
                'file_name' => 'welcome.txt',
                'original_name' => 'Welcome to Sanchaya.txt',
                'extension' => 'txt',
                'mime_type' => 'text/plain',
                'size' => 128,
                'content' => "Welcome to Filament Sanchaya File Manager!\nEnjoy managing your files effortlessly.",
                'metadata' => ['description' => 'Getting started instructions'],
            ],
        ];

        foreach ($samples as $sample) {
            $content = $sample['content'];
            unset($sample['content']);

            Storage::disk($disk)->put($sample['path'], $content);

            $fileModel::firstOrCreate(
                [
                    'parent_id' => $sample['parent_id'],
                    'disk' => $disk,
                    'file_name' => $sample['file_name'],
                ],
                array_merge($sample, [
                    'type' => 'file',
                    'disk' => $disk,
                ])
            );
        }
    }
}
