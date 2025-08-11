<?php

namespace Drupal\ai_sorting\Service;

/**
 * Service to generate and retrieve experiment UUIDs from view/display IDs.
 */
class ExperimentResolver {

  /**
   * Generates a deterministic UUID from view and display IDs.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The display ID.
   *
   * @return string
   *   A deterministic UUID for the experiment.
   */
  public function generateExperimentUuid($view_id, $display_id) {
    return sha1($view_id . ':' . $display_id);
  }

  /**
   * Extracts view and display IDs from an experiment UUID.
   *
   * Note: This method cannot reverse the SHA1 hash. Use only if you need
   * to validate or when you have additional metadata.
   *
   * @param string $experiment_uuid
   *   The experiment UUID.
   *
   * @return array|null
   *   Array with 'view_id' and 'display_id' keys, or NULL if not reversible.
   */
  public function extractViewInfo($experiment_uuid) {
    // SHA1 is one-way, so we can't reverse it.
    // This method is here for interface completeness and future enhancement.
    return NULL;
  }

}
