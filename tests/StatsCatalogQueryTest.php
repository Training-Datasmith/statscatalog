<?php

class StatsCatalogQueryTest extends StatsCatalogTestCase
{
    public function testGetQuery1CountsActiveShopProductsAndMeanPrice()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10.000000, 1);
        $this->insertImage(1, 1);
        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 30.000000, 1);
        $this->insertImage(2, 2);
        $this->insertProduct(3);
        $this->insertProductShop(3, 1, 100, 0);
        $this->insertProduct(4);
        $this->insertProductShop(4, 2, 999, 1);

        $module = $this->newModule();
        $result = $module->getQuery1();
        $this->assertInternalType('array', $result);
        $this->assertSame(2, (int) $result['total']);
        $this->assertSame(2, (int) $result['images']);
        $this->assertInternalType('string', $result['average_price']);
        $this->assertNotEmpty($result['average_price']);
        $this->assertEquals(20.0, (float) $result['average_price'], '', 0.00001);
    }

    public function testGetQuery1ImageCountIsDistinctWhenProductHasSeveralImages()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertImage(1, 1);
        $this->insertImage(2, 1);
        $this->insertImage(3, 1);

        $result = $this->newModule()->getQuery1();
        $this->assertSame(1, (int) $result['total']);
        $this->assertSame(3, (int) $result['images']);
    }

    public function testGetQuery1EmptyCatalogReturnsZeroCountsAndNullAverage()
    {
        $result = $this->newModule()->getQuery1();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('average_price', $result);
        $this->assertArrayHasKey('images', $result);
        $this->assertSame(0, (int) $result['total']);
        $this->assertSame(0, (int) $result['images']);
        $this->assertNull($result['average_price']);
    }

    public function testGetTotalPageViewedIgnoresNonProductPageTypes()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProductPage(1, 'product', 1, 4);
        $this->insertProductPage(2, 'category', 1, 100);

        $value = $this->newModule()->getTotalPageViewed();
        $this->assertInternalType('string', $value);
        $this->assertEquals(4.0, (float) $value, '', 0.00001);
    }

    public function testGetTotalPageViewedAcceptsLegacyProductPhpName()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProductPage(1, 'product.php', 1, 3);

        $value = $this->newModule()->getTotalPageViewed();
        $this->assertEquals(3.0, (float) $value, '', 0.00001);
    }

    public function testGetTotalPageViewedIsNullWhenNothingViewed()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);

        $value = $this->newModule()->getTotalPageViewed();
        $this->assertNull($value);
    }

    public function testGetTotalProductViewedCountsDistinctActiveProducts()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProductPage(1, 'product', 1, 1);
        $this->insertProductPage(2, 'product', 1, 1);
        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 10, 1);
        $this->insertProductPage(3, 'product', 2, 5);
        $this->insertProduct(3);
        $this->insertProductShop(3, 1, 10, 1);
        $this->insertProduct(99);
        $this->insertProductShop(99, 1, 10, 0);
        $this->insertProductPage(4, 'product', 99, 9);

        $value = $this->newModule()->getTotalProductViewed();
        $this->assertEquals(2.0, (float) $value, '', 0.00001);
    }

    public function testGetTotalBoughtSumsValidOrderQuantitiesOnly()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 10, 1);
        $this->insertOrder(1, 1);
        $this->insertOrderDetail(1, 1, 1, 2);
        $this->insertOrder(2, 1);
        $this->insertOrderDetail(2, 2, 2, 5);
        $this->insertOrder(3, 0);
        $this->insertOrderDetail(3, 3, 1, 9);

        $value = $this->newModule()->getTotalBought();
        $this->assertEquals(7.0, (float) $value, '', 0.00001);
    }

    public function testGetProductsNBListsUnboughtActiveProductsInRequestedLanguage()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProductLang(1, 1, 1, 'Mug', 'mug');
        $this->insertProductLang(1, 2, 1, 'Tasse', 'tasse');
        $this->insertProductLang(1, 1, 2, 'OtherShop', 'other');
        $this->insertOrder(1, 0);
        $this->insertOrderDetail(1, 1, 1, 1);
        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 10, 1);
        $this->insertProductLang(2, 1, 1, 'Sold', 'sold');
        $this->insertOrder(2, 1);
        $this->insertOrderDetail(2, 2, 2, 1);
        $this->insertProduct(3);
        $this->insertProductShop(3, 1, 10, 0);
        $this->insertProductLang(3, 1, 1, 'Hidden', 'hidden');

        $result = $this->newModule()->getProductsNB(1);
        $this->assertCount(1, $result['result']);
        $this->assertSame('1', $result['result'][0]['id_product']);
        $this->assertSame('Mug', $result['result'][0]['name']);
        $this->assertSame('mug', $result['result'][0]['link_rewrite']);
        $this->assertSame(1, (int) $result['total']);
        $this->assertSame(count($result['result']), (int) $result['total']);
    }

    public function testGetProductsNBWithNoSalesReturnsEveryActiveProduct()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProductLang(1, 1, 1, 'One', 'one');
        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 10, 1);
        $this->insertProductLang(2, 1, 1, 'Two', 'two');

        $result = $this->newModule()->getProductsNB(1);
        $this->assertSame(2, (int) $result['total']);
        $this->assertSame(count($result['result']), (int) $result['total']);
        $ids = array();
        foreach ($result['result'] as $row) {
            $ids[] = (int) $row['id_product'];
        }
        sort($ids);
        $this->assertSame(array(1, 2), $ids);
    }
}
