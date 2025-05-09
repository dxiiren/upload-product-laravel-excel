<?php

namespace App\Jobs;

use App\Imports\ProductImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ImportProductsFromExcelJob implements ShouldQueue
{
    use Queueable;

    public int $sheetCount = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $filePath) {
        $this->sheetCount = $this->getSheetCount();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->sheetCount <= 0) {
            throw new \RuntimeException("❌ Excel file has no sheets.");
        }

        Excel::import(
            new ProductImport(),
            $this->filePath, // ✅ relative path
            config('filesystems.default', 'local'),
            \Maatwebsite\Excel\Excel::XLSX
        );
    
        Storage::delete($this->filePath);
    }

    private function getSheetCount(): int
    {
        $fullPath = Storage::path($this->filePath);
        $reader = IOFactory::createReaderForFile($fullPath);
        $spreadsheet = $reader->load($fullPath);
        $sheetCount = $spreadsheet->getSheetCount();
        return $sheetCount;
    }
}
