<?php

namespace SS6\ShopBundle\TestsDb\Model\Product;

use DateTime;
use SS6\ShopBundle\Component\Test\DatabaseTestCase;
use SS6\ShopBundle\DataFixtures\Demo\CategoryDataFixture;
use SS6\ShopBundle\Model\Pricing\Vat\Vat;
use SS6\ShopBundle\Model\Pricing\Vat\VatData;
use SS6\ShopBundle\Model\Product\Product;
use SS6\ShopBundle\Model\Product\ProductDomain;
use SS6\ShopBundle\Model\Product\ProductEditData;

class ProductVisibilityRepositoryTest extends DatabaseTestCase {

	/**
	 * @return \SS6\ShopBundle\Model\Product\ProductEditData
	 */
	private function getDefaultProductEditData() {
		$category = $this->getReference(CategoryDataFixture::ELECTRONICS);

		$em = $this->getEntityManager();
		$vat = new Vat(new VatData('vat', 21));
		$em->persist($vat);

		$productEditData = new ProductEditData();
		$productEditData->productData->name = ['cs' => 'Name'];
		$productEditData->productData->vat = $vat;
		$productEditData->productData->price = 100;
		$productEditData->productData->hidden = false;
		$productEditData->productData->hiddenOnDomains = [];
		$productEditData->productData->categories = [$category];

		return $productEditData;
	}

	public function testIsVisibleOnAnyDomainWhenHidden() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');

		$productEditData = $this->getDefaultProductEditData();
		$productEditData->productData->hidden = true;
		$product = $productEditFacade->create($productEditData);

		$em->flush();
		$id = $product->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$productAgain = $em->getRepository(Product::class)->find($id);
		/* @var $productAgain \SS6\ShopBundle\Model\Product\Product */

		$productDomain1 = $em->getRepository(ProductDomain::class)->findOneBy([
			'product' => $productAgain,
			'domainId' => 1,
		]);
		/* @var $productDomain1 \SS6\ShopBundle\Model\Product\ProductDomain */

		$this->assertFalse($productAgain->isVisible());
		$this->assertFalse($productDomain1->isVisible());
	}

	public function testIsVisibleOnAnyDomainWhenNotHidden() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');

		$productEditData = $this->getDefaultProductEditData();
		$product = $productEditFacade->create($productEditData);

		$em->flush();
		$id = $product->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$productAgain = $em->getRepository(Product::class)->find($id);
		/* @var $productAgain \SS6\ShopBundle\Model\Product\Product */

		$productDomain1 = $em->getRepository(ProductDomain::class)->findOneBy([
			'product' => $productAgain->getId(),
			'domainId' => 1,
		]);
		/* @var $productDomain1 \SS6\ShopBundle\Model\Product\ProductDomain */

		$this->assertTrue($productAgain->isVisible());
		$this->assertTrue($productDomain1->isVisible());
	}

	public function testIsVisibleOnAnyDomainWhenSellingInFuture() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');

		$sellingFrom = new DateTime('now');
		$sellingFrom->modify('+1 day');

		$productEditData = $this->getDefaultProductEditData();
		$productEditData->productData->sellingFrom = $sellingFrom;
		$product = $productEditFacade->create($productEditData);

		$em->flush();
		$id = $product->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$productAgain = $em->getRepository(Product::class)->find($id);
		/* @var $productAgain \SS6\ShopBundle\Model\Product\Product */

		$this->assertFalse($productAgain->isVisible());
	}

	public function testIsVisibleOnAnyDomainWhenSellingInPast() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');

		$sellingTo = new DateTime('now');
		$sellingTo->modify('-1 day');

		$productEditData = $this->getDefaultProductEditData();
		$productEditData->productData->sellingTo = $sellingTo;
		$product = $productEditFacade->create($productEditData);

		$em->flush();
		$id = $product->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$productAgain = $em->getRepository(Product::class)->find($id);
		/* @var $productAgain \SS6\ShopBundle\Model\Product\Product */

		$this->assertFalse($productAgain->isVisible());
	}

	public function testIsVisibleOnAnyDomainWhenSellingNow() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');

		$sellingFrom = new DateTime('now');
		$sellingFrom->modify('-1 day');
		$sellingTo = new DateTime('now');
		$sellingTo->modify('+1 day');

		$productEditData = $this->getDefaultProductEditData();
		$productEditData->productData->sellingFrom = $sellingFrom;
		$productEditData->productData->sellingTo = $sellingTo;
		$product = $productEditFacade->create($productEditData);

		$em->flush();
		$id = $product->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$productAgain = $em->getRepository(Product::class)->find($id);
		/* @var $productAgain \SS6\ShopBundle\Model\Product\Product */

		$this->assertTrue($productAgain->isVisible());
	}

	public function testIsNotVisibleWhenZeroOrNullPrice() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');

		$productEditData = $this->getDefaultProductEditData();
		$productEditData->productData->price = 0;
		$product1 = $productEditFacade->create($productEditData);

		$productEditData->productData->price = null;
		$product2 = $productEditFacade->create($productEditData);

		$product1Id = $product1->getId();
		$product2Id = $product2->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$product1Again = $em->getRepository(Product::class)->find($product1Id);
		/* @var $product1Again \SS6\ShopBundle\Model\Product\Product */
		$product2Again = $em->getRepository(Product::class)->find($product2Id);
		/* @var $product2Again \SS6\ShopBundle\Model\Product\Product */

		$this->assertFalse($product1Again->isVisible());
		$this->assertFalse($product2Again->isVisible());
	}

	public function testIsVisibleWithEmptyName() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');
		/* @var $productEditFacade \SS6\ShopBundle\Model\Product\ProductEditFacade */

		$productEditData = $this->getDefaultProductEditData();
		$productEditData->productData->name = ['cs' => 'Name'];
		$product = $productEditFacade->create($productEditData);

		$productId = $product->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$productFromDb = $em->getRepository(Product::class)->find($productId);
		/* @var $productFromDb \SS6\ShopBundle\Model\Product\Product */

		$productDomain1 = $em->getRepository(ProductDomain::class)->find([
			'product' => $productId,
			'domainId' => 1,
		]);
		/* @var $productDomain1 \SS6\ShopBundle\Model\Product\ProductDomain */

		$productDomain2 = $em->getRepository(ProductDomain::class)->find([
			'product' => $productId,
			'domainId' => 2,
		]);
		/* @var $productDomain2 \SS6\ShopBundle\Model\Product\ProductDomain */

		$this->assertTrue($productFromDb->isVisible());
		$this->assertTrue($productDomain1->isVisible());
		$this->assertFalse($productDomain2->isVisible());
	}

	public function testIsVisibleAccordingToVisibilityOfCategory() {
		$em = $this->getEntityManager();
		$productEditFacade = $this->getContainer()->get('ss6.shop.product.product_edit_facade');
		/* @var $productEditFacade \SS6\ShopBundle\Model\Product\ProductEditFacade */

		$category = $this->getReference(CategoryDataFixture::TOYS);

		$productEditData = $this->getDefaultProductEditData();
		$productEditData->productData->categories = [$category];
		$product = $productEditFacade->create($productEditData);

		$productId = $product->getId();
		$em->clear();

		$productVisibilityRepository = $this->getContainer()->get('ss6.shop.product.product_visibility_repository');
		/* @var $productVisibilityRepository \SS6\ShopBundle\Model\Product\ProductVisibilityRepository */
		$productVisibilityRepository->refreshProductsVisibility();

		$productFromDb = $em->getRepository(Product::class)->find($productId);
		/* @var $productFromDb \SS6\ShopBundle\Model\Product\Product */

		$productDomain1 = $em->getRepository(ProductDomain::class)->find([
			'product' => $productId,
			'domainId' => 1,
		]);
		/* @var $productDomain1 \SS6\ShopBundle\Model\Product\ProductDomain */

		$productDomain2 = $em->getRepository(ProductDomain::class)->find([
			'product' => $productId,
			'domainId' => 2,
		]);
		/* @var $productDomain2 \SS6\ShopBundle\Model\Product\ProductDomain */

		$this->assertTrue($productFromDb->isVisible());
		$this->assertTrue($productDomain1->isVisible());
		$this->assertFalse($productDomain2->isVisible());
	}

}
