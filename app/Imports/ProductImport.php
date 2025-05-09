<?php

namespace App\Imports;

use App\Models\Product;
use App\Enums\ProductStatusEnum;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    public function chunkSize(): int
    {
        return 100;
    }

    public function collection(Collection $rows): void
    {
        $changes = $this->calculateNetChanges($rows);

        if (empty($changes))
            return;

        $upserts = $this->buildUpsertData($changes);

        if (empty($upserts))
            return;

        Product::upsert($upserts, ['id'], ['quantity']);
    }

    /**
     * Build upsert data for the products.
     *
     * @param array<int, int> $changes  Associative array of product_id => quantity change
     * @return array<int, array{product_id: int, quantity: int}> $payload
     */
    private function buildUpsertData(array $changes): array
    {
        $existingProducts = Product::select('id', 'quantity')->whereIntegerInRaw('id', array_keys($changes))
            ->get()
            ->keyBy('id');

        $upserts = [];

        foreach ($changes as $productId => $netChange) {

            if (!isset($existingProducts[$productId])) {
                continue;
            }

            $quantity = $existingProducts[$productId]->quantity + $netChange;

            if ($quantity == 0) {
                continue;
            }

            $upserts[] = [
                'id' => $productId,
                'quantity' => $quantity,
                'type' => '',
                'brand' => '',
                'model' => '',
                'capacity' => '',
            ];
        }

        return $upserts;
    }

    /**
     * Calculate net changes for each product based on the status.
     *
     * @param Collection $rows
     * @return array<int, int> $changes  Associative array of product_id => quantity change
     */
    private function calculateNetChanges(Collection $rows): array
    {
        $changes = [];

        foreach ($rows as $row) {
            $productId = $row['product_id'] ?? null;
            $status = strtolower(trim($row['status'] ?? ''));

            if (!$productId || !$status) {
                continue;
            }

            $changes[$productId] = ($changes[$productId] ?? 0) + match ($status) {
                ProductStatusEnum::SOLD->value => -1,
                ProductStatusEnum::BUY->value => 1,
                default => 0,
            };
        }

        return $changes;
    }
}
