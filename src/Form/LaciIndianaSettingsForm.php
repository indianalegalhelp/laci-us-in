<?php

/**
 * This file is part of LACI.
 *
 * LACI is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 *
 * LACI is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License along with LACI.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright 2025-2026, Indiana Legal Help
 */

namespace Drupal\laci_indiana\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Configure LACI Indiana settings.
 */
class LaciIndianaSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() : array {
    return ['laci_indiana.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'laci_indiana_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) : array {
    $config = $this->config('laci_indiana.settings');

    $form['ic_year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Indiana Code year'),
      '#description' => $this->t('The year to use when fetching Indiana Code HTML from IGA (e.g., 2025). This corresponds to the year in the URL pattern: iga.in.gov/ic/{year}/Title_{N}.html'),
      '#default_value' => $config->get('ic_year') ?: '2025',
      '#required' => TRUE,
      '#size' => 10,
      '#maxlength' => 4,
      '#pattern' => '\d{4}',
    ];

    // Load the full taxonomy tree (2 levels deep).
    $tree = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadTree('authoritativesourcetype', 0, 2, FALSE);

    $enabled_types = $config->get('enabled_authority_types') ?: [];

    // Build a hierarchical structure: parents and their children.
    $parents = [];
    $children = [];
    foreach ($tree as $term) {
      if ($term->depth == 0) {
        $parents[$term->tid] = $term->name;
      }
      else {
        $children[$term->parents[0]][$term->tid] = $term->name;
      }
    }

    // Sort children naturally (e.g., "Title 1" before "Title 10").
    foreach ($children as $parent_tid => &$child_terms) {
      uasort($child_terms, function ($a, $b) {
        // Extract leading number for natural sort.
        $num_a = preg_match('/^(\d+)/', $a, $m) ? (int) $m[1] : 0;
        $num_b = preg_match('/^(\d+)/', $b, $m) ? (int) $m[1] : 0;
        if ($num_a !== $num_b) {
          return $num_a <=> $num_b;
        }
        return strnatcasecmp($a, $b);
      });
    }
    unset($child_terms);

    $form['authority_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled Authority Types'),
      '#description' => $this->t('Select which authority types are available when creating authority sources. Expand a parent type to pick specific sub-types.'),
      '#tree' => TRUE,
    ];

    // Parent-level checkboxes.
    $form['authority_types']['parents'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Authority Types'),
      '#options' => $parents,
      '#default_value' => array_filter($enabled_types, fn($id) => isset($parents[$id])),
    ];

    // Child term fieldsets for each parent that has children.
    foreach ($children as $parent_tid => $child_terms) {
      $parent_name = $parents[$parent_tid] ?? 'Parent';
      $form['authority_types']['children_' . $parent_tid] = [
        '#type' => 'details',
        '#title' => $this->t('Sub-types for @parent', ['@parent' => $parent_name]),
        '#open' => in_array($parent_tid, $enabled_types) ? TRUE : FALSE,
        '#states' => [
          'visible' => [
            ':input[name="authority_types[parents][' . $parent_tid . ']"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['authority_types']['children_' . $parent_tid]['terms'] = [
        '#type' => 'checkboxes',
        '#options' => $child_terms,
        '#default_value' => array_filter($enabled_types, fn($id) => isset($child_terms[$id])),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) : void {
    $year = $form_state->getValue('ic_year');
    if (!preg_match('/^\d{4}$/', $year)) {
      $form_state->setErrorByName('ic_year', $this->t('Year must be a 4-digit number.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    $authority_types = $form_state->getValue('authority_types') ?: [];

    // Collect enabled parent term IDs.
    $enabled = array_filter($authority_types['parents'] ?: []);

    // Collect enabled child term IDs from each children fieldset.
    foreach ($authority_types as $key => $value) {
      if (str_starts_with($key, 'children_') && isset($value['terms'])) {
        $child_enabled = array_filter($value['terms'] ?: []);
        $enabled = array_merge($enabled, $child_enabled);
      }
    }

    $this->config('laci_indiana.settings')
      ->set('ic_year', $form_state->getValue('ic_year'))
      ->set('enabled_authority_types', array_values(array_map('intval', $enabled)))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
