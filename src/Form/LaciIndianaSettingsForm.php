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

    // Authority type checkboxes.
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadTree('authoritativesourcetype', 0, 1, FALSE);
    $enabled_types = $config->get('enabled_authority_types') ?: [];

    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    $form['enabled_authority_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled Authority Types'),
      '#description' => $this->t('Select which authority types are available when creating authority sources. Unchecked types will be hidden from the dropdown.'),
      '#options' => $options,
      '#default_value' => $enabled_types,
    ];

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
    $enabled = array_filter($form_state->getValue('enabled_authority_types') ?: []);
    $this->config('laci_indiana.settings')
      ->set('ic_year', $form_state->getValue('ic_year'))
      ->set('enabled_authority_types', array_values($enabled))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
