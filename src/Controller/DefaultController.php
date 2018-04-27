<?php /**
 * @file
 * Contains \Drupal\block_commenter\Controller\DefaultController.
 */

namespace Drupal\block_commenter\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the block_commenter module.
 */
class DefaultController extends ControllerBase {

  public function block_commenter_multiple_select_autocomplete($string = '') {
    $array = drupal_explode_tags($string);
    $last_string = trim(array_pop($array));
    $matches = [];
    if ($last_string != '') {
      $prefix = count($array) ? drupal_implode_tags($array) . ', ' : '';
      $query = db_select('users', 'u');
      $users_return = $query
        ->fields('u', ['uid', 'name'])
        ->condition('u.name', db_like($last_string) . '%', 'LIKE')
        ->range(0, 10)
        ->execute()
        ->fetchAllKeyed();
      foreach ($users_return as $uid => $name) {
        $prefix = count($array) ? implode(', ', $array) . ',' : '';
        $matches[$prefix . $name . ':' . $uid] = \Drupal\Component\Utility\Html::escape($name);
      }
    }
    drupal_json_output($matches);
  }

}
