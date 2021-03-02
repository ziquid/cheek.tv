<?php

/**
 * @file
 * Contains post-update functions for Lightning Media.
 */

/**
 * Change plugin IDs of actions.
 */
function lightning_media_post_update_change_action_plugins() {
  $action_map = [
    'media_publish_action' => 'entity:publish_action:media',
    'media_unpublish_action' => 'entity:unpublish_action:media',
    'media_save_action' => 'entity:save_action:media',
    'media_delete_action' => 'entity:delete_action:media',
  ];

  $actions = Drupal::entityTypeManager()->getStorage('action')->loadMultiple();
  /** @var \Drupal\system\ActionConfigEntityInterface $action */
  foreach ($actions as $action) {
    $plugin_id = $action->get('plugin');

    if (array_key_exists($plugin_id, $action_map)) {
      $action->setPlugin($action_map[$plugin_id]);
      $action->save();
    }
  }
}
