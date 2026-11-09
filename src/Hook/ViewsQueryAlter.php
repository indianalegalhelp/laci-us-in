<?php

namespace Drupal\laci_indiana\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Implements hook_views_query_alter().
 */
class ViewsQueryAlter {

  /**
   * Alters Views queries for authority type filtering and flagging join fixes.
   */
  #[Hook('views_query_alter')]
  public function alter(ViewExecutable $view, QueryPluginBase $query): void {
    // Fix flagging join type mismatch on document views.
    // The flagging.entity_id column is varchar but node_field_data.nid is bigint,
    // causing "operator does not exist: bigint = character varying" errors.
    $document_views = ['all_documents', 'your_documents'];
    if (in_array($view->id(), $document_views)) {
      foreach ($query->getTableQueue() as $alias => $info) {
        if (isset($info['table']) && $info['table'] instanceof JoinPluginBase) {
          $table_name = $info['table']->table;
          if ($table_name === 'flagging' && strpos($alias, 'flagging_') === 0) {
            // Cast entity_id to integer to match nid type.
            $info['table']->field = 'CAST(' . $table_name . '.entity_id AS integer)';
          }
        }
      }
    }

    // Filter authority source views by enabled authority types.
    // Only views that list authority source nodes have field_type.
    $authority_views = [
      'all_authorities',
      'authorities_with_errors',
      'authority_errors',
    ];
    if (!in_array($view->id(), $authority_views)) {
      return;
    }

    $allowed = _laci_indiana_get_allowed_type_ids();
    if ($allowed === NULL) {
      return;
    }

    // Add a JOIN to node__field_type and a WHERE condition.
    $configuration = [
      'left_table' => 'node_field_data',
      'left_field' => 'nid',
      'field' => 'entity_id',
      'table' => 'node__field_type',
    ];
    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $configuration);
    $query->ensureTable('node__field_type', 'node_field_data', $join);

    $query->addWhere('laci_indiana_filter', 'node__field_type.field_type_target_id', $allowed, 'IN');
  }

}
