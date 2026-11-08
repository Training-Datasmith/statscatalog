<?php
/**
 * PrestaShop core doubles for statscatalog integration tests.
 */

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "mysqli extension is required\n");
    exit(1);
}

if (!extension_loaded('simplexml')) {
    fwrite(STDERR, "simplexml extension is required\n");
    exit(1);
}

class Module
{
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $displayName;
    public $description;
    public $ps_versions_compliancy;
    public $installShouldSucceed = true;
    public $recordedHooks = array();

    /** @var Context */
    public $context;

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function install()
    {
        return (bool) $this->installShouldSucceed;
    }

    public function registerHook($hookName)
    {
        $this->recordedHooks[] = array('register', $hookName);

        return true;
    }

    public function unregisterHook($hookName)
    {
        $this->recordedHooks[] = array('unregister', $hookName);

        return true;
    }

    protected function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        unset($domain, $locale);
        if (isset($parameters['legacy'])) {
            unset($parameters['legacy']);
        }
        if (empty($parameters)) {
            return $id;
        }

        return vsprintf($id, $parameters);
    }
}

class Shop
{
    public static $context_id_shop = 1;

    public static function addSqlAssociation($table, $alias, $inner_join = true, $on = null, $force_not_default = false)
    {
        unset($table, $inner_join, $on, $force_not_default);
        $shopId = (int) self::$context_id_shop;
        if ($shopId === 0) {
            $shopId = 1;
        }

        return ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` product_shop
				ON (product_shop.id_product = ' . $alias . '.id_product AND product_shop.id_shop = ' . $shopId . ')';
    }

    public static function addSqlRestrictionOnLang($alias = null)
    {
        $shopId = 1;
        if (isset(Context::getContext()->shop) && Context::getContext()->shop->id) {
            $shopId = (int) Context::getContext()->shop->id;
        }
        $escaped = Db::getInstance(true)->escape($alias);

        return ' AND ' . $escaped . '.id_shop = ' . $shopId . ' ';
    }
}

class Db
{
    /** @var Db */
    private static $instance;

    /** @var mysqli */
    private $link;

    /** @var mysqli_result|null */
    private $lastResult;

    public static function getInstance($master = true)
    {
        unset($master);
        if (self::$instance === null) {
            self::$instance = new Db();
            self::$instance->connect();
        }

        return self::$instance;
    }

    public static function resetInstance()
    {
        if (self::$instance !== null) {
            self::$instance->disconnect();
            self::$instance = null;
        }
    }

    private function connect()
    {
        $host = getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1';
        $port = getenv('DB_PORT') ? (int) getenv('DB_PORT') : 3306;
        $user = getenv('DB_USER') ? getenv('DB_USER') : 'root';
        $pass = getenv('DB_PASS') ? getenv('DB_PASS') : 'statscatalog';
        $name = getenv('DB_NAME') ? getenv('DB_NAME') : 'statscatalog';

        $this->link = mysqli_connect($host, $user, $pass, $name, $port);
        if (!$this->link) {
            throw new RuntimeException('mysqli connect failed: ' . mysqli_connect_error());
        }
        if (!mysqli_query($this->link, "SET NAMES 'utf8'")) {
            throw new RuntimeException('SET NAMES utf8 failed: ' . mysqli_error($this->link));
        }
        if (!mysqli_query($this->link, "SET SESSION sql_mode=''")) {
            throw new RuntimeException('SET sql_mode failed: ' . mysqli_error($this->link));
        }
    }

    private function disconnect()
    {
        if ($this->link) {
            mysqli_close($this->link);
            $this->link = null;
        }
    }

    public function escape($value)
    {
        $value = (string) $value;
        if (preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            return $value;
        }

        return mysqli_real_escape_string($this->link, $value);
    }

    public function query($sql)
    {
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            throw new RuntimeException('SQL error: ' . mysqli_error($this->link) . ' in ' . $sql);
        }

        return $result;
    }

    public function getRow($sql)
    {
        $sql = rtrim($sql, " \t\n\r\0\x0B;") . ' LIMIT 1';
        $result = $this->query($sql);
        if ($result === true) {
            return false;
        }
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row === null) {
            return false;
        }

        return $row;
    }

    public function getValue($sql)
    {
        $row = $this->getRow($sql);
        if ($row === false) {
            return false;
        }

        return array_shift($row);
    }

    public function executeS($sql, $array = true, $use_cache = true)
    {
        unset($use_cache);
        if (!$array) {
            return $this->query($sql);
        }
        $this->lastResult = $this->query($sql);
        $rows = array();
        if (method_exists($this->lastResult, 'fetch_all')) {
            $rows = $this->lastResult->fetch_all(MYSQLI_ASSOC);
        } else {
            while ($row = mysqli_fetch_assoc($this->lastResult)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function numRows()
    {
        if ($this->lastResult === null) {
            return 0;
        }

        return mysqli_num_rows($this->lastResult);
    }

    public function ensureSchema()
    {
        $ddl = array(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'product` (
                `id_product` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_product`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'product_shop` (
                `id_product` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `price` DECIMAL(20,6) NOT NULL,
                `active` TINYINT NOT NULL,
                PRIMARY KEY (`id_product`, `id_shop`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'image` (
                `id_image` INT UNSIGNED NOT NULL,
                `id_product` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_image`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'category_product` (
                `id_category` INT UNSIGNED NOT NULL,
                `id_product` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_category`, `id_product`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'product_lang` (
                `id_product` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `link_rewrite` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id_product`, `id_lang`, `id_shop`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'page` (
                `id_page` INT UNSIGNED NOT NULL,
                `id_page_type` INT UNSIGNED NOT NULL,
                `id_object` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_page`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'page_type` (
                `id_page_type` INT UNSIGNED NOT NULL,
                `name` VARCHAR(64) NOT NULL,
                PRIMARY KEY (`id_page_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'page_viewed` (
                `id_page` INT UNSIGNED NOT NULL,
                `counter` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_page`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'orders` (
                `id_order` INT UNSIGNED NOT NULL,
                `valid` TINYINT NOT NULL,
                PRIMARY KEY (`id_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'order_detail` (
                `id_order_detail` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_order` INT UNSIGNED NOT NULL,
                `product_id` INT UNSIGNED NOT NULL,
                `product_quantity` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_order_detail`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        );
        foreach ($ddl as $sql) {
            $this->query($sql);
        }
    }

    public function truncateAll()
    {
        $tables = array(
            'order_detail',
            'orders',
            'page_viewed',
            'page',
            'page_type',
            'category_product',
            'product_lang',
            'image',
            'product_shop',
            'product',
        );
        foreach ($tables as $table) {
            $this->query('TRUNCATE TABLE `' . _DB_PREFIX_ . $table . '`');
        }
    }
}

class Tools
{
    public static function getValue($key, $default_value = false)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default_value;
    }

    public static function safeOutput($string, $html = false)
    {
        if (!$html) {
            $string = strip_tags($string);
        }

        return htmlentities((string) $string, ENT_QUOTES, 'utf-8');
    }
}

class Category
{
    /** @var array */
    public static $fixtures = array();

    public static function getCategories($idLang = false, $active = true, $order = true)
    {
        unset($active, $order);
        if (isset(self::$fixtures[(int) $idLang])) {
            return self::$fixtures[(int) $idLang];
        }

        return array();
    }
}

class StatsCatalogLinkStub
{
    public function getAdminLink($controller, $withToken = true, $sfRoute = array(), $params = array())
    {
        unset($controller, $withToken, $sfRoute);
        $update = isset($params['updateproduct']) ? (int) $params['updateproduct'] : 0;
        $id = isset($params['id_product']) ? (int) $params['id_product'] : 0;

        return 'https://admin.test/AdminProducts?updateproduct=' . $update . '&id_product=' . $id;
    }

    public function getProductLink($id, $rewrite)
    {
        return 'https://shop.test/' . (int) $id . '-' . $rewrite;
    }
}

class StatsCatalogLocaleStub
{
    /** @var array */
    public $formatPriceCalls = array();

    public function formatPrice($number, $iso)
    {
        if (!is_string($number) && !is_int($number) && !is_float($number)) {
            throw new InvalidArgumentException('formatPrice expects string|int|float');
        }
        $this->formatPriceCalls[] = array($number, $iso);

        return number_format((float) $number, 2, '.', '') . ' ' . $iso;
    }
}

class StatsCatalogShopStub
{
    public $id = 1;
}

class StatsCatalogLanguageStub
{
    public $id;

    public function __construct($id)
    {
        $this->id = (int) $id;
    }
}

class StatsCatalogCurrencyStub
{
    public $iso_code;

    public function __construct($iso)
    {
        $this->iso_code = $iso;
    }
}

class Context
{
    /** @var Context */
    private static $instance;

    /** @var StatsCatalogLanguageStub */
    public $language;

    /** @var StatsCatalogCurrencyStub */
    public $currency;

    /** @var StatsCatalogLinkStub */
    public $link;

    /** @var StatsCatalogShopStub */
    public $shop;

    /** @var StatsCatalogLocaleStub */
    private $locale;

    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new Context();
        }

        return self::$instance;
    }

    public static function resetContext()
    {
        self::$instance = new Context();
        self::$instance->language = new StatsCatalogLanguageStub(1);
        self::$instance->currency = new StatsCatalogCurrencyStub('EUR');
        self::$instance->link = new StatsCatalogLinkStub();
        self::$instance->shop = new StatsCatalogShopStub();
        self::$instance->locale = new StatsCatalogLocaleStub();
    }

    public function getCurrentLocale()
    {
        return $this->locale;
    }
}
