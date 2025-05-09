<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Data\ProductData;
use App\Imports\ProductImport;
use Illuminate\Http\UploadedFile;
use Database\Seeders\ProductSeeder;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ImportProductsFromExcelJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_can_get_all_products()
    {
        //prepare
        Product::factory()->count(20)->create([
            'brand' => 'Apple',
            'model' => 'iPhone SE',
        ]);

        //test
        $response = $this->getJson('/api/products');

        //assert
        $response->assertOk();
        $response->assertJsonCount(10, 'data.data');
    }

    public function test_can_get_products_with_filters()
    {
        //prepare
        Product::factory()->count(3)->create([
            'brand' => 'Apple',
            'model' => 'iPhone SE',
        ]);

        Product::factory()->count(5)->create([
            'brand' => 'Samsung',
            'model' => 'Galaxy S21',
        ]);

        Product::factory()->count(3)->create([
            'brand' => 'Apple',
            'model' => 'iPhone 12',
        ]);

        //test
        $response = $this->getJson('/api/products?search=Apple');

        //assert
        $response->assertOk();

        foreach ($response->json('data.data') as $product) {
            $this->assertEquals('Apple', $product['brand']);
            $this->assertStringContainsString('iPhone', $product['model']);
        }
    }

    public function test_can_store_product()
    {
        //prepare
        $productData = ProductData::from([
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        //test
        $response = $this->postJson('/api/products', $productData->toArray());

        //assert
        $response->assertCreated();
        $response->assertJsonFragment($productData->toArray());
        $this->assertDatabaseHas('products', $productData->toArray());
    }

    public function test_can_update_product()
    {
        //prepare
        $product = Product::factory()->create([
            'id' => 99999,
            'capacity' => '2GB/64GB',
            'quantity' => 10,
        ]);

        $productData = ProductData::from([
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/128GB',
            'quantity' => 20,
        ]);

        //test
        $response = $this->putJson('/api/products/' . $product->id, $productData->toArray());

        //assert
        $response->assertOk();
        $response->assertJsonFragment($productData->toArray());
        $this->assertDatabaseHas('products', [
            'id' => 99999,
            'capacity' => '2GB/128GB',
            'quantity' => 20,
        ]);
    }

    public function test_can_delete_product()
    {
        //prepare
        $product = Product::factory()->create([
            'id' => 4452,
            'capacity' => '2GB/64GB',
            'quantity' => 10,
        ]);

        //test
        $response = $this->deleteJson('/api/products/' . $product->id);

        //assert
        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => 4452]);
    }

    public function test_cant_store_product_with_no_quantity()
    {
        //prepare
        $productData = ProductData::from([
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        $payload = $productData->toArray();
        unset($payload['quantity']);

        //test
        $response = $this->postJson('/api/products', $payload);

        //assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_cant_update_product_with_no_quantity()
    {
        //prepare
        $product = Product::factory()->create([
            'id' => 4453,
            'capacity' => '2GB/64GB',
            'quantity' => 10,
        ]);

        $productData = ProductData::from([
            'id' => 4453,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/128GB',
            'quantity' => 20,
        ]);

        $payload = $productData->toArray();
        unset($payload['quantity']);

        //test
        $response = $this->putJson('/api/products/' . $product->id, $payload);

        //assert
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_can_import_products()
    {
        // Prepare
        Queue::fake();

        $payload = new UploadedFile(
            database_path('seeders/product_status_list.xlsx'),
            'product_sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        //test
        $response = $this->postJson('/api/products/import', [
            'file' => $payload,
        ]);

        //assert
        $response->assertOk();
        $response->assertJson([
            'message' => 'Uploading is in process and submitted successfully',
        ]);

        Queue::assertPushed(ImportProductsFromExcelJob::class);
    }

    public function test_cant_import_products_with_invalid_file()
    {
        // Prepare
        Storage::fake('local');

        $fakeUploadedFile = UploadedFile::fake()->create(
            'product_sample.txt',
            10,
            'text/plain'
        );

        //test
        $response = $this->postJson('/api/products/import', [
            'file' => $fakeUploadedFile,
        ]);

        //assert
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_import_products_from_excel_job_runs_successfully()
    {
        // Prepare
        Excel::fake();

        $storedPath = 'imports/' . uniqid() . '.xlsx';

        Storage::disk(config('filesystems.default', 'local'))->put(
            $storedPath,
            file_get_contents(database_path('seeders/product_status_list.xlsx'))
        );

        // Test
        dispatch(new ImportProductsFromExcelJob($storedPath));

        // Assert
        Excel::assertImported(
            $storedPath,
            config('filesystems.default', 'local'),
            fn($import) => $import instanceof ProductImport,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function test_import_products_from_excel_job_actually_creates_products()
    {
        [$seederProduct, $expectedProduct] = $this->getAssertAndExpectedProductForExcelImport();

        foreach ($seederProduct as $product) {
            $this->assertDatabaseHas('products', [
                'id' => $product['id'],
                'quantity' => $product['quantity'],
            ]);
        }

        // Prepare
        $storedPath = 'imports/test_product_import.xlsx';

        Storage::disk(config('filesystems.default', 'local'))
            ->put($storedPath, file_get_contents(database_path('seeders/product_status_list.xlsx')));

        // Test
        dispatch(new ImportProductsFromExcelJob($storedPath));

        // Assert
        foreach ($expectedProduct as $product) {
            $this->assertDatabaseHas('products', [
                'id' => $product['id'],
                'quantity' => $product['quantity'],
            ]);
        }

        Storage::disk(config('filesystems.default', 'local'))->assertMissing($storedPath);
    }

    public function test_import_product_from_endpoint()
    {
        // Prepare
        [$seederProduct, $expectedProduct] = $this->getAssertAndExpectedProductForExcelImport();

        foreach ($seederProduct as $product) {
            $this->assertDatabaseHas('products', [
                'id' => $product['id'],
                'quantity' => $product['quantity'],
            ]);
        }

        $payload = new UploadedFile(
            database_path('seeders/product_status_list.xlsx'),
            'product_sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        //test
        $response = $this->postJson('/api/products/import', [
            'file' => $payload,
        ]);

        //assert
        $response->assertOk();
        $response->assertJson([
            'message' => 'Uploading is in process and submitted successfully',
        ]);

        foreach ($expectedProduct as $product) {
            $this->assertDatabaseHas('products', [
                'id' => $product['id'],
                'quantity' => $product['quantity'],
            ]);
        }
    }

    private function getAssertAndExpectedProductForExcelImport(): array
    {
        $seederProduct = [
            [
                'id' => 4450,
                'quantity' => 13,
            ],
            [
                'id' => 4768,
                'quantity' => 30,
            ],
            [
                'id' => 4451,
                'quantity' => 20,
            ],
            [
                'id' => 4574,
                'quantity' => 16,
            ],
            [
                'id' => 6039,
                'quantity' => 18,
            ],
        ];

        $expectedProduct = [
            [
                'id' => 4450,
                'quantity' => 13 - 2 + 1, //12
            ],
            [
                'id' => 4768,
                'quantity' => 30 - 2 + 1, //29
            ],
            [
                'id' => 4451,
                'quantity' => 20 + 2 - 2, //20
            ],
            [
                'id' => 4574,
                'quantity' => 16 - 2 + 1, //15
            ],
            [
                'id' => 6039,
                'quantity' => 18 + 4, //22
            ],
        ];

        return [
            $seederProduct,
            $expectedProduct,
        ];
    }
}
