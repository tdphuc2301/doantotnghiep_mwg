<?php

namespace App\Services;

use App\Events\ChangeMetaSeo;
use App\Events\InsertNewRecord;
use App\Exceptions\UploadImageException;
use App\Http\Services\UploadImageService;
use App\Models\Color_product;
use App\Models\Product;
use App\Models\Product_color;
use App\Repositories\ProductRepository;
use App\Repositories\ImageRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ProductService
{
    private $productRepository;
    private $imageRepository;
    private $uploadImageService;
    private $colorService;

    /**
     * @param ProductRepository $productRepository
     * @param UploadImageService $uploadImageService
     * @param ImageRepository $imageRepository
     * @return void
     */
    public function __construct(
        ProductRepository $productRepository,
        UploadImageService $uploadImageService,
        ImageRepository $imageRepository,
        ColorService $colorService
    ) {
        $this->productRepository = $productRepository;
        $this->uploadImageService = $uploadImageService;
        $this->imageRepository = $imageRepository;
        $this->colorService = $colorService;
    }

    public function paginateAll(
        int $page,
        int $limit,
        array $data = [],
        string $sortKey,
        int $sortValue
    ): LengthAwarePaginator {
        $filter = [];
        $fillableProperties = $this->productRepository->getFillableProperties();
        foreach ($data as $key => $value) {
            if (in_array($key, $fillableProperties) && !is_null($value)) {
                $filter[$key] = $value;
            }
        }
        if(!empty($data['keyword'])){
            $filter['search'] = [
                'operator' => 'LIKE',
                'value' => "%". $data['keyword']. "%"
            ];
        }
        $user_branch_id = Auth::user()->branch_id;
        if(isset($user_branch_id)) {
            $filter['branch_id'] = $user_branch_id;
        }
        $searchCriteria = [
            'page' => $page,
            'limit' => $limit,
            'sort' => $sortValue ? $sortKey : "-$sortKey",
            "filter" => $filter,
        ];
        return $this->productRepository->paginateAllProduct(
            $searchCriteria
        );
    }

    /**
     * @param int $id
     * @return Model|null
     */
    public function findProduct(int $id): ?Model
    {
        return $this->productRepository->findProduct($id);
    }

    /**
     * @param array $data
     * @return null|Model
     */
    public function createProduct(array $data): ?Model
    {
        if (!empty($data['id'])) {
            $product = $this->productRepository->findOne($data['id']);
            $product = $this->productRepository->update($product, $data);
        } else {
            $array_rand_rate = [ 3 => 1, 4 => 2, 5=>3];
            $array_rand_total_rate = [ 100 => 1, 250 => 2, 130=>3,400=>4,450=>5];

            $product = $this->productRepository->save([
                'category_id' => $data['category_id'],
                'ram_id' => $data['ram_id'],
                'rom_id' => $data['rom_id'],
                'brand_id' => $data['brand_id'],
                'name' => $data['name'],
                'code' => $data['code'] ?? '',
                'index' => $data['index'] ?? config('common.default_index'),
                'short_description' => $data['short_description'] ?? '',
                'description' => $data['description'] ?? '',
                'price' => $data['price'],
                'sale_off_price' => $data['sale_off_price'] ?? 0,
                'rate' => array_rand($array_rand_rate) ?? 0,
                'total_rate' => array_rand($array_rand_total_rate) ?? 0,
                'status' => $data['status'] ?? config('common.status.active')
            ]);
            $colors = [1,2,3,4,5,6];
            foreach ($colors as $color) {
                $product_color = new Color_product();
                $product_color->product_id = $product->id;
                $product_color->color_id = $color;
                $product_color->save();
            }
        }

        if (!empty($product->id)) {
            // Create alias
            event(new InsertNewRecord($product, $data['alias'] ?? $product->name));
            if (!empty($data['remove_images'])) {
                $this->removeProductImage($product, $data['remove_images']);
            }
            if (!empty($data['images'])) {
                foreach ($data['images'] as $index => $image) {
                    if (isUploadFile($image ?? null)) {
                        $this->updateProductImage($product, $image, $index);
                    }
                }
            }
            // Create meta seo
            if (!empty($data['meta_seo'])) {
                event(new ChangeMetaSeo($product, $data['meta_seo']));
            }
            return $product;
        }
        return null;
    }

    /**
     * @param Product $product
     * @param UploadedFile $image
     * @param int $index
     * @return void
     */
    protected function updateProductImage(Product $product, UploadedFile $image, int $index = 1): void
    {
        $uploadImage = $this->uploadImageService
            ->setModule('product')
            ->setWidth(config('image.resize.product.width'))
            ->setHeight(config('image.resize.product.height'))
            ->uploadImage($image, null, true);

        if ($uploadImage->isSuccess()) {
            $uploadImage = $uploadImage->getData();
            $this->removeProductImage($product, [$index]);
            $this->imageRepository->updateOrCreate(
                [
                    'model_id' => $product->id,
                    'model_type' => get_class($product),
                    'index' => $index,
                ],
                [
                    'width' => $uploadImage['width'] ?? null,
                    'height' => $uploadImage['height'] ?? null,
                    'size' => $uploadImage['size'] ?? null,
                    'path' => $uploadImage['path'] ?? null,
                ]
            );
        } else {
            throw new UploadImageException($uploadImage->getMessage());
        }
    }

    /**
     * @param Product $product
     * @param array $indexs
     * @return void
     */
    public function removeProductImage(Product $product, array $indexs = []): void
    {
        if ($indexs) {
            $images = $product->getImagesByIndex($indexs);
        } else {
            $images = $product->images;
        }
        /**
         * @param Image $image
         */
        foreach ($images as $image) {
            $this->uploadImageService->removeFile(public_path($image->path));
            $image->delete();
        }
    }

    /**
     * @param int $id
     * @param bool $status
     * @return bool
     */
    public function changeStatus(int $id, bool $status): bool
    {
        $product = $this->productRepository->findOne($id);
        if ($product) {
            $this->productRepository->update($product, [
                'status' => $status
            ]);
            return true;
        }
        return false;
    }
}
