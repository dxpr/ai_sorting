<?php

namespace Drupal\ai_sorting\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to override cache headers for AI sorting pages.
 */
class AiSortingCacheSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse', -10];
    return $events;
  }

  /**
   * Modifies cache headers for pages with AI sorting.
   */
  public function onResponse(ResponseEvent $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();
    
    // Check if this is a page that might contain AI sorting
    $route_name = $request->attributes->get('_route');
    
    // For now, let's target the specific route that has AI sorting
    // You can expand this logic to be more sophisticated
    if ($request->getPathInfo() === '/blog/test') {
      
      // Check if response has cache headers to modify
      $cache_control = $response->headers->get('Cache-Control');
      
      if ($cache_control && strpos($cache_control, 'max-age=300') !== false) {
        // Replace the site-wide 300 seconds with AI sorting's 30 seconds
        $new_cache_control = str_replace('max-age=300', 'max-age=30', $cache_control);
        $response->headers->set('Cache-Control', $new_cache_control);
        
        // Also set the response max age directly
        if ($response instanceof CacheableResponseInterface) {
          $response->setMaxAge(30);
        }
      }
    }
  }

}