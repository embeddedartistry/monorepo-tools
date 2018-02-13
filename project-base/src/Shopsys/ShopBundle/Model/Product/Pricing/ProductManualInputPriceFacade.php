<?php

namespace Shopsys\ShopBundle\Model\Product\Pricing;

use Doctrine\ORM\EntityManager;
use Shopsys\ShopBundle\Model\Pricing\Group\PricingGroup;
use Shopsys\ShopBundle\Model\Product\Product;

class ProductManualInputPriceFacade
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var \Shopsys\ShopBundle\Model\Product\Pricing\ProductManualInputPriceRepository
     */
    private $productManualInputPriceRepository;

    /**
     * @var \Shopsys\ShopBundle\Model\Product\Pricing\ProductManualInputPriceService
     */
    private $productManualInputPriceService;

    public function __construct(
        EntityManager $em,
        ProductManualInputPriceRepository $productManualInputPriceRepository,
        ProductManualInputPriceService $productManualInputPriceService
    ) {
        $this->em = $em;
        $this->productManualInputPriceRepository = $productManualInputPriceRepository;
        $this->productManualInputPriceService = $productManualInputPriceService;
    }

    /**
     * @param \Shopsys\ShopBundle\Model\Product\Product $product
     * @param \Shopsys\ShopBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param string $inputPrice
     */
    public function refresh(Product $product, PricingGroup $pricingGroup, $inputPrice)
    {
        $manualInputPrice = $this->productManualInputPriceRepository->findByProductAndPricingGroup($product, $pricingGroup);
        $refreshedProductManualInputPrice = $this->productManualInputPriceService->refresh(
            $product,
            $pricingGroup,
            $inputPrice,
            $manualInputPrice
        );
        $this->em->persist($refreshedProductManualInputPrice);
        $this->em->flush($refreshedProductManualInputPrice);
    }

    /**
     * @param \Shopsys\ShopBundle\Model\Product\Product $product
     * @return \Shopsys\ShopBundle\Model\Product\Pricing\ProductManualInputPrice[]
     */
    public function getAllByProduct(Product $product)
    {
        return $this->productManualInputPriceRepository->getByProduct($product);
    }

    /**
     * @param \Shopsys\ShopBundle\Model\Product\Product $product
     */
    public function deleteByProduct(Product $product)
    {
        $manualInputPrices = $this->productManualInputPriceRepository->getByProduct($product);
        foreach ($manualInputPrices as $manualInputPrice) {
            $this->em->remove($manualInputPrice);
        }
        $this->em->flush($manualInputPrices);
    }
}