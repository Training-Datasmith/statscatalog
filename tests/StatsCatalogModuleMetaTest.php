<?php

class StatsCatalogModuleMetaTest extends StatsCatalogTestCase
{
    public function testConstructorSetsModuleMetadata()
    {
        $module = $this->newModule();
        $this->assertSame('statscatalog', $module->name);
        $this->assertSame('administration', $module->tab);
        $this->assertSame('2.0.5', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
        $this->assertSame('Catalog statistics', $module->displayName);
        $expected = 'Enrich your stats, have a look at your catalog’s general statistics.';
        $this->assertSame($expected, $module->description);
        $this->assertSame(array('min' => '1.7.6.0', 'max' => '1.7.6.0'), $module->ps_versions_compliancy);
    }

    public function testInstallRegistersStatsHookWhenParentInstallSucceeds()
    {
        $module = $this->newModule();
        $module->installShouldSucceed = true;
        $this->assertTrue($module->install());
        $this->assertSame(array(array('register', 'displayAdminStatsModules')), $module->recordedHooks);
    }

    public function testInstallDoesNotRegisterHookWhenParentInstallFails()
    {
        $module = $this->newModule();
        $module->installShouldSucceed = false;
        $this->assertFalse($module->install());
        $this->assertSame(array(), $module->recordedHooks);
    }

    public function testUpgrade202MovesLegacyHook()
    {
        $module = $this->newModule();
        $this->assertTrue(upgrade_module_2_0_2($module));
        $this->assertSame(
            array(
                array('unregister', 'AdminStatsModules'),
                array('register', 'displayAdminStatsModules'),
            ),
            $module->recordedHooks
        );
    }

    public function testConfigXmlVersionMatchesClass()
    {
        $xml = simplexml_load_file($this->repoRoot . '/config.xml');
        $this->assertInstanceOf('SimpleXMLElement', $xml);
        $this->assertSame('statscatalog', (string) $xml->name);
        $this->assertSame('2.0.5', (string) $xml->version);
        $module = $this->newModule();
        $this->assertSame((string) $xml->version, $module->version);
    }
}
