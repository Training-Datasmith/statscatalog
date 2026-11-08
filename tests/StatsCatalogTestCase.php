<?php

abstract class StatsCatalogTestCase extends PHPUnit_Framework_TestCase
{
  protected $repoRoot;

  protected function setUp()
  {
    $this->repoRoot = dirname(__DIR__);
    $_GET = array();
    $_POST = array();
    Shop::$context_id_shop = 1;
    Context::resetContext();
    Category::$fixtures = array(
      1 => array(
        array('id_category' => 5, 'name' => 'Mugs'),
        array('id_category' => 9, 'name' => 'Other'),
      ),
    );
    Db::getInstance(true)->truncateAll();
  }

  protected function newModule()
  {
    return new statscatalog();
  }

  protected function db()
  {
    return Db::getInstance(true);
  }

  protected function insertProduct($id)
  {
    $this->db()->query('INSERT INTO `' . _DB_PREFIX_ . 'product` (`id_product`) VALUES (' . (int) $id . ')');
  }

  protected function insertProductShop($productId, $shopId, $price, $active)
  {
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'product_shop` (`id_product`, `id_shop`, `price`, `active`) VALUES ('
      . (int) $productId . ', ' . (int) $shopId . ', ' . (float) $price . ', ' . (int) $active . ')'
    );
  }

  protected function insertProductLang($productId, $langId, $shopId, $name, $rewrite)
  {
    $db = $this->db();
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'product_lang` (`id_product`, `id_lang`, `id_shop`, `name`, `link_rewrite`) VALUES ('
      . (int) $productId . ', ' . (int) $langId . ', ' . (int) $shopId . ', '
      . "'" . $db->escape($name) . "', '" . $db->escape($rewrite) . "')"
    );
  }

  protected function insertImage($imageId, $productId)
  {
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'image` (`id_image`, `id_product`) VALUES ('
      . (int) $imageId . ', ' . (int) $productId . ')'
    );
  }

  protected function insertCategoryProduct($categoryId, $productId)
  {
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'category_product` (`id_category`, `id_product`) VALUES ('
      . (int) $categoryId . ', ' . (int) $productId . ')'
    );
  }

  protected function insertPageType($typeId, $name)
  {
    $db = $this->db();
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'page_type` (`id_page_type`, `name`) VALUES ('
      . (int) $typeId . ", '" . $db->escape($name) . "')"
    );
  }

  protected function insertPage($pageId, $typeId, $objectId)
  {
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'page` (`id_page`, `id_page_type`, `id_object`) VALUES ('
      . (int) $pageId . ', ' . (int) $typeId . ', ' . (int) $objectId . ')'
    );
  }

  protected function insertPageViewed($pageId, $counter)
  {
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'page_viewed` (`id_page`, `counter`) VALUES ('
      . (int) $pageId . ', ' . (int) $counter . ')'
    );
  }

  protected function insertOrder($orderId, $valid)
  {
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'orders` (`id_order`, `valid`) VALUES ('
      . (int) $orderId . ', ' . (int) $valid . ')'
    );
  }

  protected function insertOrderDetail($detailId, $orderId, $productId, $quantity)
  {
    $this->db()->query(
      'INSERT INTO `' . _DB_PREFIX_ . 'order_detail` (`id_order_detail`, `id_order`, `product_id`, `product_quantity`) VALUES ('
      . (int) $detailId . ', ' . (int) $orderId . ', ' . (int) $productId . ', ' . (int) $quantity . ')'
    );
  }

  protected function insertProductPage($pageId, $typeName, $productId, $counter)
  {
    $typeId = $pageId + 1000;
    $this->insertPageType($typeId, $typeName);
    $this->insertPage($pageId, $typeId, $productId);
    if ($counter !== null) {
      $this->insertPageViewed($pageId, $counter);
    }
  }

  protected function getLocaleCalls()
  {
    return Context::getContext()->getCurrentLocale()->formatPriceCalls;
  }
}
