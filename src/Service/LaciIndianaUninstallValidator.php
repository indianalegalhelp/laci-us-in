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

namespace Drupal\laci_indiana\Service;

use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\laci_core\Service\LaciCoreUninstallValidator;

/**
 * Prevents module from being uninstalled when terms it provides are referenced.
 */
class LaciIndianaUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * The LACI Core Uninstall Validator.
   *
   * @var \Drupal\Core\Entity\LaciCoreUninstallValidator
   */
  protected $laciCoreUninstaller;

  /**
   * Constructs a new LaciIndianaUninstallValidator.
   *
   * @param \Drupal\Core\Entity\LaciCoreUninstallValidator $laci_core_uninstall_validator
   *   The LACI Core Uninstall Validator.
   */
  public function __construct(LaciCoreUninstallValidator $laci_core_uninstall_validator) {
    $this->laciCoreUninstaller = $laci_core_uninstall_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];

    if ('laci_indiana' === $module) {
      $hasAuthoritiesWithTerms = FALSE;

      foreach ($this->laciCoreUninstaller->getTermRootsFromConfig($module . '.authoritytype.default_terms') as $root) {
        if ($this->laciCoreUninstaller->hasAuthoritiesWithRoot($root)) {
          $hasAuthoritiesWithTerms = TRUE;
          break;
        }
      }

      if ($hasAuthoritiesWithTerms) {
        $reasons[] = $this->t('Authorit(ies) with terms exist');
      }
    }

    return $reasons;
  }

}
