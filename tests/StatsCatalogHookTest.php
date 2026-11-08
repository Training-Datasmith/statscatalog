<?php

class StatsCatalogHookTest extends StatsCatalogTestCase
{
    public function testHookRendersAggregatesForUnfilteredCatalog()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertImage(1, 1);
        $this->insertProductLang(1, 1, 1, 'Alpha', 'alpha');
        $this->insertCategoryProduct(5, 1);
        $this->insertProductPage(1, 'product', 1, 4);
        $this->insertOrder(1, 1);
        $this->insertOrderDetail(1, 1, 1, 2);
        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 30, 1);
        $this->insertImage(2, 2);
        $this->insertProductLang(2, 1, 1, 'Beta', 'beta');
        $this->insertCategoryProduct(5, 2);
        $this->insertProduct(3);
        $this->insertProductShop(3, 1, 100, 0);
        $this->insertProduct(4);
        $this->insertProductShop(4, 2, 999, 1);

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());

        $this->assertContains('Products available:<span class="badge">2</span>', $html);
        $this->assertContains('Average price (base price):<span class="badge">20.00 EUR</span>', $html);
        $this->assertContains('Product pages viewed:<span class="badge">4</span>', $html);
        $this->assertContains('Products bought:<span class="badge">2</span>', $html);
        $this->assertContains('Average number of page visits:<span class="badge">2.00</span>', $html);
        $this->assertContains('Average number of purchases:<span class="badge">1.00</span>', $html);
        $this->assertContains('Images available:<span class="badge">2</span>', $html);
        $this->assertContains('Average number of images:<span class="badge">1.00</span>', $html);
        $this->assertContains('Products never viewed:<span class="badge">1 / 2</span>', $html);
        $this->assertContains('Products never purchased:<span class="badge">1 / 2</span>', $html);
        $this->assertContains('Conversion rate*:<span class="badge">0.50(1 purchase / 2 visits)</span>', $html);
        $this->assertContains("$('#calendar').slideToggle();", $html);
        $this->assertContains('Catalog statistics', $html);
        $this->assertContains('Beta', $html);
        $this->assertNotContains('Alpha', $html);

        $calls = $this->getLocaleCalls();
        $this->assertCount(1, $calls);
        $this->assertInternalType('float', $calls[0][0]);
        $this->assertEquals(20.0, $calls[0][0], '', 0.00001);
        $this->assertSame('EUR', $calls[0][1]);
    }

    public function testHookPassesFloatZeroPriceWhenCatalogIsEmpty()
    {
        Category::$fixtures = array(1 => array());

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());

        $calls = $this->getLocaleCalls();
        $this->assertCount(1, $calls);
        $this->assertInternalType('float', $calls[0][0]);
        $this->assertSame(0.0, $calls[0][0]);
        $this->assertSame('EUR', $calls[0][1]);
        $this->assertContains('0.00 EUR', $html);
        $this->assertContains('Products available:<span class="badge">0</span>', $html);
        $this->assertContains('Product pages viewed:<span class="badge">0</span>', $html);
        $this->assertContains('Products bought:<span class="badge">0</span>', $html);
        $this->assertContains('Images available:<span class="badge">0</span>', $html);
        $this->assertContains('Average number of page visits:<span class="badge">0.00</span>', $html);
        $this->assertContains('Average number of purchases:<span class="badge">0.00</span>', $html);
        $this->assertContains('Products never viewed:<span class="badge">0 / 0</span>', $html);
        $this->assertContains('Products never purchased:<span class="badge">0 / 0</span>', $html);
        $this->assertContains('>0.00</span>', $html);
        $this->assertNotContains('1 purchase /', $html);
        $this->assertNotContains('<div class="panel-heading">Products never purchased</div>', $html);
    }

    public function testHookOmitsPurchaseSuffixWhenViewsExistButNothingBought()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertImage(1, 1);
        $this->insertProductLang(1, 1, 1, 'Lonely', 'lonely');
        $this->insertProductPage(1, 'product', 1, 5);

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());

        $this->assertContains('>0.00</span>', $html);
        $this->assertNotContains('1 purchase /', $html);
        $this->assertContains('Products bought:<span class="badge">0</span>', $html);
        $this->assertContains('Average number of purchases:<span class="badge">0.00</span>', $html);
        $this->assertContains('Average number of page visits:<span class="badge">5.00</span>', $html);
    }

    public function testHookFiltersByCategoryId()
    {
        $this->seedThreeCategoryProducts();
        $_GET['id_category'] = '5';

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());

        $this->assertContains('Products available:<span class="badge">2</span>', $html);
        $this->assertContains('Product pages viewed:<span class="badge">4</span>', $html);
        $this->assertContains('Products bought:<span class="badge">2</span>', $html);
        $this->assertContains('InCatUnsold', $html);
        $this->assertNotContains('OutCat', $html);
        $this->assertContains('value="5" selected="selected"', $html);
        $this->assertNotContains('value="9" selected="selected"', $html);
    }

    public function testHookIgnoresNonNumericCategory()
    {
        $this->seedThreeCategoryProducts();
        $_GET['id_category'] = 'abc';

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('Products available:<span class="badge">3</span>', $html);
    }

    public function testHookCastsCategoryIdBeforeFiltering()
    {
        $this->seedThreeCategoryProducts();
        $_GET['id_category'] = '5 OR 1=1';

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('Products available:<span class="badge">2</span>', $html);
        $this->assertNotContains('OutCat', $html);
    }

    public function testHookShowsTableWhenFortyNineNeverPurchased()
    {
        Category::$fixtures = array(1 => array());
        for ($i = 1; $i <= 49; $i++) {
            $this->insertProduct($i);
            $this->insertProductShop($i, 1, 10, 1);
            $this->insertProductLang($i, 1, 1, 'P' . $i, 'p' . $i);
        }

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('Products never purchased:<span class="badge">49 / 49</span>', $html);
        $this->assertContains('<div class="panel-heading">Products never purchased</div>', $html);
    }

    public function testHookHidesTableWhenFiftyNeverPurchased()
    {
        Category::$fixtures = array(1 => array());
        for ($i = 1; $i <= 50; $i++) {
            $this->insertProduct($i);
            $this->insertProductShop($i, 1, 10, 1);
            $this->insertProductLang($i, 1, 1, 'P' . $i, 'p' . $i);
        }

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('Products never purchased:<span class="badge">50 / 50</span>', $html);
        $this->assertNotContains('<div class="panel-heading">Products never purchased</div>', $html);
    }

    public function testHookAlternatesRowClass()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProductLang(1, 1, 1, 'Red', 'red');
        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 10, 1);
        $this->insertProductLang(2, 1, 1, 'Blue', 'blue');

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertSame(1, substr_count($html, 'alt_row'));
        $this->assertContains('Red', $html);
        $this->assertContains('Blue', $html);
        $tbodyPos = strpos($html, '<tbody>');
        $this->assertNotSame(false, $tbodyPos);
        $afterTbody = substr($html, $tbodyPos);
        $firstTr = strpos($afterTbody, '<tr');
        $this->assertNotSame(false, $firstTr);
        $firstRow = substr($afterTbody, $firstTr, 120);
        $this->assertNotContains('alt_row', $firstRow);
        $secondTr = strpos($afterTbody, '<tr', $firstTr + 3);
        $this->assertNotSame(false, $secondTr);
        $secondRow = substr($afterTbody, $secondTr, 120);
        $this->assertContains('alt_row', $secondRow);
    }

    public function testHookEscapesAdminAndProductLinks()
    {
        $this->insertProduct(7);
        $this->insertProductShop(7, 1, 10, 1);
        $this->insertProductLang(7, 1, 1, 'Quoted', 'a"b');

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('updateproduct=1&amp;id_product=7', $html);
        $this->assertContains('https://shop.test/7-a&quot;b', $html);
    }

    public function testHookUsesContextCurrencyAndLanguage()
    {
        $ctx = Context::getContext();
        $ctx->language = new StatsCatalogLanguageStub(2);
        $ctx->currency = new StatsCatalogCurrencyStub('USD');
        Category::$fixtures = array(
            2 => array(array('id_category' => 3, 'name' => 'OnlyLangTwo')),
        );

        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertProductLang(1, 1, 1, 'LangOneName', 'one');
        $this->insertProductLang(1, 2, 1, 'LangTwoName', 'two');

        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('LangTwoName', $html);
        $this->assertContains('OnlyLangTwo', $html);
        $this->assertNotContains('LangOneName', $html);
        $calls = $this->getLocaleCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('USD', $calls[0][1]);
    }

    private function seedThreeCategoryProducts()
    {
        $this->insertProduct(1);
        $this->insertProductShop(1, 1, 10, 1);
        $this->insertImage(1, 1);
        $this->insertProductLang(1, 1, 1, 'InCat', 'incat');
        $this->insertCategoryProduct(5, 1);
        $this->insertProductPage(1, 'product', 1, 4);
        $this->insertOrder(1, 1);
        $this->insertOrderDetail(1, 1, 1, 2);

        $this->insertProduct(2);
        $this->insertProductShop(2, 1, 10, 1);
        $this->insertImage(2, 2);
        $this->insertProductLang(2, 1, 1, 'InCatUnsold', 'incatunsold');
        $this->insertCategoryProduct(5, 2);

        $this->insertProduct(3);
        $this->insertProductShop(3, 1, 10, 1);
        $this->insertImage(3, 3);
        $this->insertProductLang(3, 1, 1, 'OutCat', 'outcat');
        $this->insertCategoryProduct(9, 3);
        $this->insertProductPage(3, 'product', 3, 10);
        $this->insertOrder(2, 1);
        $this->insertOrderDetail(2, 2, 3, 8);
    }
}
