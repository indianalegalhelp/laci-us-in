<?php

/**
 * @file
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

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Per-user preferences for which authority types are visible.
 */
class LaciUserPreferencesForm extends FormBase {

  /**
   * The user data service.
   */
  protected UserDataInterface $userData;

  /**
   * The user being edited.
   */
  protected ?UserInterface $user = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->userData = $container->get('user.data');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'laci_indiana_user_preferences';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL): array {
    $this->user = $user;

    // Load the full taxonomy tree (2 levels deep).
    $tree = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadTree('authoritativesourcetype', 0, 2, FALSE);

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

    // Natural sort children (Title 1 before Title 10).
    foreach ($children as $parent_tid => &$child_terms) {
      uasort($child_terms, function ($a, $b) {
        preg_match('/^(?:Title\s+)?(\d+)/', $a, $ma);
        preg_match('/^(?:Title\s+)?(\d+)/', $b, $mb);
        $num_a = isset($ma[1]) ? (int) $ma[1] : 0;
        $num_b = isset($mb[1]) ? (int) $mb[1] : 0;
        if ($num_a !== $num_b) {
          return $num_a <=> $num_b;
        }
        return strnatcasecmp($a, $b);
      });
    }
    unset($child_terms);

    // Get current user prefs (or defaults).
    $enabled = $this->getUserEnabledTypes($this->user);

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Select which authority types appear in your Create Authority dropdown. Unchecked types will be hidden from your view only.') . '</p>',
    ];

    $form['authority_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Visible Authority Types'),
      '#tree' => TRUE,
    ];

    // Render parent types with children nested.
    foreach ($parents as $tid => $name) {
      $form['authority_types']['parent_' . $tid] = [
        '#type' => 'checkbox',
        '#title' => $name,
        '#default_value' => in_array((int) $tid, $enabled, TRUE) ? 1 : 0,
        '#attributes' => ['class' => ['laci-pref-parent']],
      ];

      if (isset($children[$tid])) {
        $form['authority_types']['children_' . $tid] = [
          '#type' => 'details',
          '#title' => $this->t('Sub-types of @parent', ['@parent' => $name]),
          '#open' => FALSE,
          '#states' => [
            'visible' => [
              ':input[name="authority_types[parent_' . $tid . ']"]' => ['checked' => TRUE],
            ],
          ],
        ];
        foreach ($children[$tid] as $child_tid => $child_name) {
          $form['authority_types']['children_' . $tid]['child_' . $child_tid] = [
            '#type' => 'checkbox',
            '#title' => $child_name,
            '#default_value' => in_array((int) $child_tid, $enabled, TRUE) ? 1 : 0,
          ];
        }
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save preferences'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $authority_types = $form_state->getValue('authority_types') ?: [];
    $enabled = [];

    foreach ($authority_types as $key => $value) {
      if (str_starts_with($key, 'parent_') && $value) {
        $tid = (int) str_replace('parent_', '', $key);
        $enabled[] = $tid;
      }
      elseif (str_starts_with($key, 'children_') && is_array($value)) {
        foreach ($value as $child_key => $child_value) {
          if (str_starts_with($child_key, 'child_') && $child_value) {
            $child_tid = (int) str_replace('child_', '', $child_key);
            $enabled[] = $child_tid;
          }
        }
      }
    }

    $this->userData->set('laci_indiana', $this->user->id(), 'enabled_authority_types', $enabled);

    $this->messenger()->addStatus($this->t('Your authority type preferences have been saved.'));
  }

  /**
   * Get the enabled authority types for a user.
   *
   * Returns the user's saved preferences, or the site defaults if none saved.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return int[]
   *   Array of enabled term IDs.
   */
  public static function getUserEnabledTypes(AccountInterface $account): array {
    $userData = \Drupal::service('user.data');
    $prefs = $userData->get('laci_indiana', $account->id(), 'enabled_authority_types');

    if ($prefs !== NULL) {
      return array_map('intval', $prefs);
    }

    // Default: use site-wide config, or if empty, all Indiana terms.
    $config = \Drupal::config('laci_indiana.settings');
    $site_enabled = $config->get('enabled_authority_types');

    if (!empty($site_enabled)) {
      return array_map('intval', $site_enabled);
    }

    // Fallback: all terms provided by laci_indiana.
    $tree = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadTree('authoritativesourcetype', 0, 2, FALSE);
    return array_map(fn($t) => (int) $t->tid, $tree);
  }

}
