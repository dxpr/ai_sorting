<?php

namespace Drupal\ai_sorting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for AI Sorting operations.
 */
class AISortingController extends ControllerBase {

  /**
   * Increments the trial count for specified nodes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function incrementTrials(Request $request) {
    $nids = json_decode($request->getContent(), TRUE)['nids'] ?? [];
    if (empty($nids) || !is_array($nids)) {
      return new JsonResponse(['error' => 'Invalid input'], 400);
    }

    $database = \Drupal::database();
    foreach ($nids as $nid) {
      $database->merge('node_counter')
        ->key(['nid' => $nid])
        ->fields(['ai_sorting_trials' => 1])
        ->expression('ai_sorting_trials', 'ai_sorting_trials + :inc', [':inc' => 1])
        ->execute();
    }

    return new JsonResponse(['success' => TRUE]);
  }

  /**
   * Tracks clicks for specified nodes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function trackClick(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    $nid = $data['nid'] ?? NULL;
    $view_id = $data['view_id'] ?? NULL;
    $display_id = $data['display_id'] ?? NULL;

    if (empty($nid) || empty($view_id) || empty($display_id)) {
      return new JsonResponse(['error' => 'Invalid input'], 400);
    }

    $database = \Drupal::database();
    $database->merge('ai_sorting_clicks')
      ->key(['nid' => $nid, 'view_id' => $view_id, 'display_id' => $display_id])
      ->fields(['clicks' => 1])
      ->expression('clicks', 'clicks + :inc', [':inc' => 1])
      ->execute();

    return new JsonResponse(['success' => TRUE]);
  }
}