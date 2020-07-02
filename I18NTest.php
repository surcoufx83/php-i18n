<?php

use PHPUnit\Framework\TestCase;
use I18N\i18n;
use I18N\L;

require_once 'i18n.class.php';

final class I18NTest extends TestCase
{

  /** @var i18n */
  private $i18n;

  protected function setUp() : void {
    parent::setUp();
    $this->i18n = new i18n('./lang/lang_{LANGUAGE}.ini');
  }

  public function testGetAppliedLangBeforeInit() : void {
    $this->assertNull($this->i18n->getAppliedLang());
  }

  public function testGetCachePath() : void {
    $this->assertEquals('./langcache/', $this->i18n->getCachePath());
    $test2 = new i18n(null, 'foo');
    $this->assertEquals('foo', $test2->getCachePath());
  }

  public function testGetFallbackLang() : void {
    $this->assertEquals('en', $this->i18n->getFallbackLang());
    $test2 = new i18n(null, null, 'foo');
    $this->assertEquals('foo', $test2->getFallbackLang());
  }

  public function testGetFilePath() : void {
    $this->assertEquals('./lang/lang_{LANGUAGE}.ini', $this->i18n->getFilePath());
    $test2 = new i18n('foo');
    $this->assertEquals('foo', $test2->getFilePath());
  }

  public function testGetForcedLang() : void {
    $this->assertNull($this->i18n->getForcedLang());
  }

  public function testGetMergeFallback() : void {
    $this->assertFalse($this->i18n->getMergeFallback());
  }

  public function testGetPrefix() : void {
    $this->assertEquals('L', $this->i18n->getPrefix());
    $test2 = new i18n(null, null, null, 'foo');
    $this->assertEquals('foo', $test2->getPrefix());
  }

  public function testGetSectionSeparator() : void {
    $this->assertEquals('_', $this->i18n->getSectionSeparator());
  }

  public function testGetStaticMap() : void {
    $this->assertEquals([], $this->i18n->getStaticMap());
  }

  /**
   * @depends testGetForcedLang
   */
  public function testGetUserLangs() : void {
    $this->assertEquals(['en'], $this->i18n->getUserLangs());
    $this->i18n->setForcedLang('de');
    $this->assertEquals(['de', 'en'], $this->i18n->getUserLangs());
    $_GET['lang'] = 'cz';
    $this->assertEquals(['de', 'cz', 'en'], $this->i18n->getUserLangs());
    $_SESSION['lang'] = 'fr';
    $this->assertEquals(['de', 'cz', 'fr', 'en'], $this->i18n->getUserLangs());
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'it,no';
    $this->assertEquals(['de', 'cz', 'fr', 'it', 'no', 'en'], $this->i18n->getUserLangs());
    $_COOKIE['lang'] = 'es';
    $this->assertEquals(['de', 'cz', 'fr', 'it', 'no', 'es', 'en'], $this->i18n->getUserLangs());

    unset($_COOKIE['lang']);
    unset($_GET['lang']);
    unset($_SESSION['lang']);
    unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
  }

  /**
   * @depends testGetUserLangs
   */
  public function testGetUserLangsWithDuplicates() : void {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de-CH';
    $this->assertEquals([0 => 'de', 2 => 'en'], $this->i18n->getUserLangs());
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-GB,en-US';
    $this->assertEquals([0 => 'en'], $this->i18n->getUserLangs());
    unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
  }

  public function testIsInitializedBeforeInit() : void {
    $this->assertFalse($this->i18n->isInitialized());
  }

  /**
   * @depends testGetAppliedLangBeforeInit
   * @depends testIsInitializedBeforeInit
   */
  public function testFinishSetup() : void {
    $this->assertTrue($this->i18n->finishSetup());
  }

  /**
   * @depends testFinishSetup
   */
  public function testsAfterInit() : void {
    $this->i18n->finishSetup();
    $this->assertEquals('en', $this->i18n->getAppliedLang());
    $this->assertTrue($this->i18n->isInitialized());
    $this->assertEquals('Hello World!', L::greeting);
    $this->assertEquals('Something other...', L::category_somethingother);
    $this->markTestSkipped('Requires newer version of PHPUNIT on travis ci.');
    //$this->expectErrorMessage('Undefined class constant \'category\'');
    //$this->assertEquals('Hello World!', L::category);
  }

  /**
   * @depends testGetCachePath
   */
  public function testSetCachePath() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setCachePath('foo'));
    $this->assertEquals('foo', $this->i18n->getCachePath());
  }

  /**
   * @depends testGetFallbackLang
   */
  public function testSetFallbackLang() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setFallbackLang('foo'));
    $this->assertEquals('foo', $this->i18n->getFallbackLang());
  }

  /**
   * @depends testGetFilePath
   */
  public function testSetFilePath() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setFilePath('foo'));
    $this->assertEquals('foo', $this->i18n->getFilePath());
  }

  /**
   * @depends testGetForcedLang
   */
  public function testForcedLang() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setForcedLang('foo'));
    $this->assertEquals('foo', $this->i18n->getForcedLang());
  }

  /**
   * @depends testGetMergeFallback
   */
  public function testSetMergeFallback() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setMergeFallback(true));
    $this->assertTrue($this->i18n->getMergeFallback());
    $this->assertInstanceOf(i18n::class, $this->i18n->setMergeFallback(false));
    $this->assertFalse($this->i18n->getMergeFallback());
  }

  /**
   * @depends testGetPrefix
   */
  public function testSetPrefix() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setPrefix('foo'));
    $this->assertEquals('foo', $this->i18n->getPrefix());
  }

  /**
   * @depends testGetSectionSeparator
   */
  public function testSetSectionSeparator() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setSectionSeparator('foo'));
    $this->assertEquals('foo', $this->i18n->getSectionSeparator());
  }

  /**
   * @depends testGetStaticMap
   */
  public function testSetStaticMap() : void {
    $this->assertInstanceOf(i18n::class, $this->i18n->setStaticMap(['foo' => 'bar']));
    $this->assertEquals(['foo' => 'bar'], $this->i18n->getStaticMap());
  }

}
