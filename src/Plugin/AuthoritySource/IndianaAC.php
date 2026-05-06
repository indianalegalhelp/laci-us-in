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
use Drupal\laci_core\AuthoritySource\AuthoritySourceBase;
use Drupal\laci_core\Entities\Authority;
use Drupal\laci_core\Exception\AuthorityParseException;

/**
 * Indiana Administrative Code Authority Source.
 *
 * PLACEHOLDER: Source research is still in progress.
 * The IAC is published at https://iar.iga.in.gov/code/current (React SPA).
 * The MyIGA API provides IAC endpoints at:
 *   GET /{year}/iac/titles
 *   GET /{year}/iac/titles/{title_number}
 *   GET /{year}/iac/titles/{title_number}/articles
 *   GET /{year}/iac/titles/{title_number}/articles/{article_number}
 *
 * Citation format (per Rule 22): 34 Ind. Admin. Code 12-5-1 / 34 I.A.C. 12-5-1
 *
 * TODO: Complete research on iar.iga.in.gov source structure and implement parse().
 */
#[AuthoritySource(
  id: 'laci_indiana:admin_code',
  rootName: 'laci_indiana_admin_code',
  label: 'Indiana Administrative Code'
)]
class IndianaAC extends AuthoritySourceBase {

  private const SECTION_RE = '/^\d+(-\d+){2,3}$/m';

  /**
   * {@inheritdoc}
   */
  public function isValid(Authority $auth) : bool {
    preg_match_all(self::SECTION_RE, $auth->getSection(), $matches, PREG_SET_ORDER, 0);
    return (bool) $matches;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPattern() : string {
    return '[:digit:]+(-[:digit:]+){2,3}';
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Authority $auth) : ?string {
    // @todo Implement once IAC source structure is determined.
    // See: https://iar.iga.in.gov/code/current
    // API: https://api.iga.in.gov/{year}/iac/titles/{N}/articles/{A}
    throw new AuthorityParseException("Indiana Administrative Code parser is not yet implemented. Source research is still in progress for iar.iga.in.gov.");
  }

}
