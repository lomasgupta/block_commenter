<?php

/**
 * @file
 * Contains \Drupal\block_commenter\Form\BlockCommenterAdminSettings.
 */

namespace Drupal\block_commenter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class BlockCommenterAdminSettings extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'block_commenter_admin_settings';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $users = block_commenter_get_blocked_users();
    $content_types = node_type_get_names();
    foreach ($content_types as $name => $content_type) {
      $options[\Drupal\Component\Utility\Html::escape($name)] = \Drupal\Component\Utility\Html::escape($content_type);
    }

    $form['block_commenter_user'] = [
      '#type' => 'textfield',
      '#title' => t('Users'),
      '#autocomplete_path' => 'block_commenter/multiple_autocomplete',
      '#description' => t('Required - Enter a comma-separated list of users which you want to block.'),
      '#default_value' => $users,
      '#size' => 120,
      '#maxlength' => 500,
      '#required' => TRUE,
    ];
    $states = [
      0 => t('Leave as it is.'),
      1 => t('Unpublish.'),
      2 => t('Delete.'),
    ];
    $form['block_commenter_comment_exist'] = [
      '#type' => 'radios',
      '#title' => t('Existing Comments By Blocked User'),
       '#default_value' => \Drupal::state()->get('block_commenter_comment_exist', '0'),
      '#options' => $states,
    ];
    $form['block_commenter_content_type'] = [
      '#type' => 'checkboxes',
      '#title' => t('Content Type'),
      '#options' => $options,
      '#description' => t('Required - Choose the content type corresponding to which you want to block the user from commenting.'),
      '#default_value' => \Drupal::state()->get('block_commenter_content_type', []),
      '#required' => TRUE,
    ];
    $form['block_commenter_submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit Settings'),
    ];

    return $form;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $exist_comment_setting = $form_state->getValue(['block_commenter_comment_exist']);
    $content_type_data = $form_state->getValue(['block_commenter_content_type']);
    $users_data = explode(',', $form_state->getValue(['block_commenter_user']));
    $content_type = [];
    $uids = [];
    //This foreach loop creates an array of blocked content types.
    foreach ($content_type_data as $name => $value) {
      if ($value) {
        $content_type[] = $value;
      }
    }
    foreach ($users_data as $record) {
      $user_data = [];
      $user_data = explode(':', $record);
      $uid = array_pop($user_data);
      $uids[] = $uid;
    }
    if (empty($uids) || empty($content_type)) {
      return;
    }
    \Drupal::state()->set('block_commenter_content_type', $content_type);
    \Drupal::state()->set('block_commenter_comment_exist', $exist_comment_setting);
    //variable_set('block_commenter_content_type', $content_type);
    //variable_set('block_commenter_comment_exist', $exist_comment_setting);
    foreach ($uids as $uid) {
      db_merge('block_commenter')
        ->key(['uid' => $uid])
        ->fields([
        'uid' => $uid,
        'timestamp' => time(),
      ])
        ->execute();
    }
    db_delete('block_commenter')
      ->condition('uid', $uids, 'NOT IN')
      ->execute();
    // For Unpublished.
    if ($exist_comment_setting == 1) {
      $result = db_query("SELECT DISTINCT(c.cid) FROM {comment} AS c INNER JOIN {node} As n WHERE c.uid IN (:uid) AND c.status=:status AND n.type IN (:content_type)", [
        ':uid' => $uids,
        ':status' => '1',
        ':content_type' => $content_type,
      ]);
      foreach ($result as $record) {
        comment_unpublish_action(NULL, ['cid' => $record->cid]);
      }
    }
      // For Delete.
    elseif ($exist_comment_setting == 2) {
      $result = db_query("SELECT DISTINCT(c.cid) FROM {comment} AS c INNER JOIN {node} As n WHERE c.uid IN (:uid) AND n.type IN (:content_type)", [
        ':uid' => $uids,
        ':content_type' => $content_type,
      ]);
      $cids = [];
      foreach ($result as $record) {
        $cids[] = $record->cid;
      }
      comment_delete_multiple($cids);
    }
    drupal_set_message(t('The settings have been saved successfully.'));
  }

}
?>
