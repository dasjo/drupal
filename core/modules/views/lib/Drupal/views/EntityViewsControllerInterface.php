<?php

/**
 * @file
 * Contains \Drupal\views\EntityViewsControllerInterface.
 */

namespace Drupal\views;

/**
 * Defines an interface for a entity views controller.
 */
interface EntityViewsControllerInterface {

  /**
   * Returns views data for a certain entity type.
   *
   * @return array
   *
   * @see hook_views_data().
   */
  public function viewsData();
}
