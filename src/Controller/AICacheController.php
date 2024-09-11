<?php

namespace Drupal\ai_sorting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class AICacheController extends ControllerBase {

  protected $cacheBackend;

  public function __construct(CacheBackendInterface $cache_backend) {
    $this->cacheBackend = $cache_backend;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('cache.ai_sorting'));
  }

  public function clearCache() {
    $this->cacheBackend->deleteAll();
    $this->messenger()->addStatus($this->t('AI Sorting cache has been cleared.'));
    return new Response($this->t('AI Sorting cache cleared successfully.'));
  }

  public function showCache() {
    $cache_items = $this->cacheBackend->getAll();
    $output = '<pre>' . print_r($cache_items, TRUE) . '</pre>';
    return new Response($output);
  }
}