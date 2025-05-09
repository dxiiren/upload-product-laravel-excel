<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\URL;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Tests\TestCase;

class ProductGraphqlTest extends TestCase
{
    use RefreshDatabase, MakesGraphQLRequests, RefreshesSchemaCache;

    public function beforeRefreshingDatabase(): void
    {
        RefreshDatabaseState::$migrated = true;
    }

    public function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl(config('app.url'));
        $this->bootRefreshesSchemaCache();
    }

    public function test_get_all_products_graphql()
    {
        //prepare
        Product::factory()->count(20)->create();

        //test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query {
                products {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            '
        );
        $response->assertOk();

        //assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertArrayHasKey('paginatorInfo', $response->json('data.products'));
        $this->assertArrayHasKey('total', $response->json('data.products.paginatorInfo'));
        $this->assertArrayHasKey('hasMorePages', $response->json('data.products.paginatorInfo'));
        $this->assertArrayHasKey('data', $response->json('data.products'));

        $this->assertCount(10, $response->json('data.products.data'));
    }

    public function test_get_product_by_id_graphql()
    {
        //prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        //test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsById($id: Mixed!) {
                products(where: {column: id, operator: EQ, value: $id}) {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            ',
            /* GraphQL Variable */
            [
                "id" => 9999
            ],
        );
        $response->assertOk();

        //assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals(9999, $response->json('data.products.data.0.id'));
    }

    public function test_get_product_by_type_graphql()
    {
        //prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        //test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!) {
                products(where: {column: type, operator: EQ, value: $type}) {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            ',
            /* GraphQL Variable */
            [
                "type" => "Smartphone"
            ],
        );
        $response->assertOk();

        //assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
    }

    public function test_find_product_using_like()
    {
        //prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        //test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!) {
                products(where: {column: type, operator: LIKE, value: $type}) {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            ',
            /* GraphQL Variable */
            [
                "type" => "Smart%"
            ],
        );
        $response->assertOk();

        //assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
    }

    public function test_using_like_for_2_columns()
    {
        // Prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        // Test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!, $brand: Mixed!) {
                products(where: {
                    AND: [
                        { column: type, operator: LIKE, value: $type }
                        { column: brand, operator: LIKE, value: $brand }
                    ]
                }) {
                    paginatorInfo {
                        total
                        hasMorePages
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                    }
                }
            }
            ',
            [
                "type" => "Smart%",
                "brand" => "App%"
            ]
        );

        $response->assertOk();

        // Assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
        $this->assertEquals('Apple', $response->json('data.products.data.0.brand'));
        $this->assertEquals(9999, $response->json('data.products.data.0.id'));
    }

    public function test_using_like_for_2_columns_with_or()
    {
        // Prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        // Test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!, $brand: Mixed!) {
                products(where: {
                    OR: [
                        { column: type, operator: LIKE, value: $type }
                        { column: brand, operator: LIKE, value: $brand }
                    ]
                }) {
                    paginatorInfo {
                        total
                        hasMorePages

                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                    }
                }
            }
            ',
            [
                "type" => "Smart%",
                "brand" => "App%"
            ]
        );

        $response->assertOk();

        // Assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
        $this->assertEquals('Apple', $response->json('data.products.data.0.brand'));
        $this->assertEquals(9999, $response->json('data.products.data.0.id'));
    }

    public function test_product_search_scope_graphql()
    {
        // Prepare
        Product::factory()->count(5)->create([
            'type' => 'Smartphone',
        ]);

        Product::factory()->create([
            'id' => 8888,
            'type' => 'Laptop',
            'brand' => 'Dell',
            'model' => 'XPS 15',
            'capacity' => '16GB/512GB',
            'quantity' => 5,
        ]);

        // Test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query SearchProducts($filter: ProductFilterInput) {
                products(filter: $filter) {
                    paginatorInfo {
                    total
                    }
                    data {
                    id
                    type
                    brand
                    model
                    }
                }
            }
            ',
            [
                'filter' => [
                    'search' => 'Smart'
                ]
            ]
        );

        $response->assertOk();

        // Assert
        $data = $response->json('data.products.data');
        $this->assertCount(5, $data);

        foreach ($data as $product) {
            $this->assertStringContainsString('Smart', $product['type']);
        }
    }
}
