<?php

use PHPUnit\Framework\TestCase;
use I18N\i18n;

require_once 'i18n.class.php';

final class I18NTest extends TestCase
{

  /** @var i18n */
  private $i18n;

  protected function setUp() : void {
    global $i18n;
    parent::setUp();
    $this->i18n = new i18n();
  }

  public function testIsInitialized() : void {
    $this->assertTrue(true);
  }

}
