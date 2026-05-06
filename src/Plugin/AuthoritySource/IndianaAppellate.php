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

namespace Drupal\laci_indiana\Plugin\AuthoritySource;

use Drupal\laci_core\Attribute\AuthoritySource;

/**
 * Indiana Rules of Appellate Procedure Authority Source.
 *
 * Source: https://rules.incourts.gov/Content/appellate/rule{N}/current.htm
 * Citation: Ind. Appellate Rule 8 / App. R. 8
 */
#[AuthoritySource(
  id: 'laci_indiana:appellate',
  rootName: 'laci_indiana_appellate',
  label: 'Indiana Rules of Appellate Procedure'
)]
class IndianaAppellate extends IndianaCourtRuleBase {

  /**
   * {@inheritdoc}
   */
  protected static function getCategory() : string {
    return 'appellate';
  }

}
