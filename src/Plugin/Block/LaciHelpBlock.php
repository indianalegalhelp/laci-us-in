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

namespace Drupal\laci_indiana\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a LACI Help sidebar block.
 *
 * @Block(
 *   id = "laci_help_block",
 *   admin_label = @Translation("LACI Help Sidebar"),
 *   category = @Translation("LACI")
 * )
 */
class LaciHelpBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $module_path = \Drupal::service('extension.list.module')->getPath('laci_indiana');
    $template_path = DRUPAL_ROOT . '/' . $module_path . '/templates/laci-help-block.html.twig';
    $template = file_get_contents($template_path);

    return [
      '#type' => 'inline_template',
      '#template' => $template ?: '<p>LACI Help: template not found</p>',
      '#attached' => [
        'library' => ['laci_indiana/help_sidebar'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
