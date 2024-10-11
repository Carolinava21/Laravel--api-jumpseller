<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Exception;


class ProductController extends Controller
{
    // Obtener productos API Fixlabs
    public function getProducts(){
            $response = Http::get('https://induccion.fixlabsdev.com/api/products');
            return $response->json();   
   
    }

    // Función que crea producto en Jumpseller // retorna ID
    private function createProduct(array $data)
    {
        // Auth Jumpseller API
        $login = env('JUMPSELLER_LOGIN');
        $auth = env('JUMPSELLER_AUTH');
        // Generar estructura Producto Jumpseller
        $product = [
            'product' => [
                'name' => $data['name'],
                'price' => $data['price'],
                'description' => $data['description'],
            ]
        ];
        try {
            
            $response = Http::withBasicAuth($login, $auth)->post('https://api.jumpseller.com/v1/products.json', $product);
            return $response->json()['product']['id'];
            
        } catch (\Exception $e){
            return null;
        }
    }
    // Crea variante de producto
    public function createVariant(array $data, int $product_id)
{
    $SKU = $data['sku'];
    $variants_created = [];

    try {
        
        $responses = Http::pool(function (Pool $pool) use ($data, $SKU, $product_id) {
            foreach ($data['variants'] as $size) {
                $pool->withBasicAuth(
                    env('JUMPSELLER_LOGIN'),
                    env('JUMPSELLER_AUTH')
                )->post("https://api.jumpseller.com/v1/products/{$product_id}/variants.json", [
                    'variant' => [
                        'sku' => "$SKU-$size",
                        'options' => [
                            [
                                'name' => 'talla',
                                'option_type' => 'option',
                                'value' => "$size"
                            ]
                        ]
                    ]
                ]);
            }
        });

        foreach ($responses as $response) {
            if ($response->successful()) {
                $variants_created[] = $response->json()['variant'];
            } else {
                return null; 
            }
        }

        return $variants_created;
    } catch (Exception $e) {
        return null;
    }
}

 
   
    // Función main que tiene toda la lógica.
    public function transferData()
    {
        // Obtenemos productos
        $products = $this->getProducts();

        foreach ($products as $product) {

            // Crea un producto en jumpseller
            $product_id = $this->createProduct($product);
            // Itera por la cantidad de variantes
            foreach ($product['variants'] as $variant_code) {
                $sku_variant = $product['sku'] . '-' . $variant_code;
                
                $variant = [
                    'variant' => [
                        'sku' => $sku_variant,
                        'stock' => 0, //TODO: obtener stock desde el otro endpoint. 
                        // Añadir options si es necesario
                    ]
                ];
               $product_variant = $this->createVariant($product, $product_id);
                


            }
        }
    }
}