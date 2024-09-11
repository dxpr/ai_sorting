<?php

namespace Drupal\Tests\ai_sorting\Unit;

use Drupal\ai_sorting\Service\UCB2Calculator;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Tests\UnitTestCase;

class UCB2CalculatorTest extends UnitTestCase {

  protected $ucb2Calculator;
  protected $database;
  protected $cache;
  protected $configFactory;
  protected $config;

  protected function setUp(): void {
    parent::setUp();
    $this->database = $this->getMockBuilder(Connection::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->cache = $this->getMockBuilder(CacheBackendInterface::class)
      ->getMock();
    $this->configFactory = $this->getMockBuilder(ConfigFactoryInterface::class)
      ->getMock();
    $this->config = $this->getMockBuilder(Config::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->configFactory->method('get')
      ->willReturn($this->config);
    $this->ucb2Calculator = new UCB2Calculator($this->database, $this->cache, $this->configFactory);
  }

  public function testCalculateWithCache() {
    $nid = 1;
    $cached_value = 0.75;
    $this->cache->expects($this->once())
      ->method('get')
      ->with("ai_sorting_ucb2:$nid")
      ->willReturn((object) ['data' => $cached_value]);
    $this->assertEquals($cached_value, $this->ucb2Calculator->calculate($nid));
  }

  public function testCalculateWithoutCache() {
    $nid = 1;
    $rewards = 10;
    $impressions = 20;
    $alpha = 0.5;
    $expected_ucb2 = (10 / 20) + sqrt((1 + 0.5) * log(21) / (2 * 20));
    $this->cache->expects($this->once())
      ->method('get')
      ->with("ai_sorting_ucb2:$nid")
      ->willReturn(FALSE);
    $this->database->expects($this->once())
      ->method('select')
      ->willReturnSelf();
    $this->database->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $this->database->expects($this->once())
      ->method('condition')
      ->willReturnSelf();
    $this->database->expects($this->once())
      ->method('execute')
      ->willReturn(new \ArrayObject(['ai_sorting_rewards' => $rewards, 'totalcount' => $impressions]));
    $this->config->expects($this->any())
      ->method('get')
      ->with('alpha')
      ->willReturn($alpha);
    $this->cache->expects($this->once())
      ->method('set')
      ->with("ai_sorting_ucb2:$nid", $expected_ucb2);
    $this->assertEquals($expected_ucb2, $this->ucb2Calculator->calculate($nid));
  }

  public function testUcb2Formula() {
    $reflection = new \ReflectionClass($this->ucb2Calculator);
    $method = $reflection->getMethod('ucb2Formula');
    $method->setAccessible(TRUE);
    $rewards = 10;
    $impressions = 20;
    $alpha = 0.5;
    $expected_ucb2 = (10 / 20) + sqrt((1 + 0.5) * log(21) / (2 * 20));
    $this->assertEquals($expected_ucb2, $method->invokeArgs($this->ucb2Calculator, [$rewards, $impressions, $alpha]));
  }

}