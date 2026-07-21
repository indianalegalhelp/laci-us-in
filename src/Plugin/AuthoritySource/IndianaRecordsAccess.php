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
 * Indiana Rules on Access to Court Records Authority Source.
 *
 * Source: https://rules.incourts.gov/Content/records/rule{N}/current.htm
 * Citation: Ind. Access to Court Records Rule 1 / Rec. R. 1
 */
#[AuthoritySource(
  id: 'laci_indiana:records_access',
  rootName: 'laci_indiana_rules_on_access_to_court_records',
  label: 'Indiana Rules on Access to Court Records'
)]
class IndianaRecordsAccess extends IndianaCourtRuleBase {

  /**
   * {@inheritdoc}
   */
  protected static function getCategory() : string {
    return 'records';
  }

}
