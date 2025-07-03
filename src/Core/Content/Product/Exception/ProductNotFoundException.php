<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ProductNotFoundException extends ProductException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::PRODUCT_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'product', 'field' => 'id', 'value' => $productId]
        );
    }
}
