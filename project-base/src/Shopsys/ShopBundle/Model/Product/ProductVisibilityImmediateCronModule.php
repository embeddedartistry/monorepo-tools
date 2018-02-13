<?php

namespace Shopsys\ShopBundle\Model\Product;

use Shopsys\Plugin\Cron\SimpleCronModuleInterface;
use Symfony\Bridge\Monolog\Logger;

class ProductVisibilityImmediateCronModule implements SimpleCronModuleInterface
{
    /**
     * @var \Shopsys\ShopBundle\Model\Product\ProductVisibilityFacade
     */
    private $productVisibilityFacade;

    public function __construct(ProductVisibilityFacade $productVisibilityFacade)
    {
        $this->productVisibilityFacade = $productVisibilityFacade;
    }

    /**
     * @inheritdoc
     */
    public function setLogger(Logger $logger)
    {
    }

    public function run()
    {
        $this->productVisibilityFacade->refreshProductsVisibilityForMarked();
    }
}