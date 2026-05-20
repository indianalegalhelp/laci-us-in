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
 * Indiana Appellate Decisions Authority Source.
 *
 * PLACEHOLDER: Source research is still in progress.
 * Opinions are published at https://public.courts.in.gov/decisions
 *
 * Citation format (per Rule 22):
 *   Published: Todd v. Coleman, 119 N.E.3d 1137 (Ind. Ct. App. 2019)
 *   Memorandum: Steele v. Taber, No. 22A-CT-925 (Ind. Ct. App. Jan. 17, 2023) (mem.)
 *
 * Alternative source: CourtListener API (https://www.courtlistener.com/api/rest/v3/)
 *
 * TODO: Complete research on public.courts.in.gov/decisions and implement parse().
 */
#[AuthoritySource(
  id: 'laci_indiana:decisions',
  rootName: 'laci_indiana_appellate_decisions',
  label: 'Indiana Appellate Decisions'
)]
class IndianaDecisions extends AuthoritySourceBase {

  /**
   * {@inheritdoc}
   */
  public function isValid(Authority $auth) : bool {
    // Case citations are complex — accept anything that looks like a case citation.
    // Published: "119 N.E.3d 1137" or similar reporter citation
    // Memorandum: "No. 22A-CT-925" style
    $section = $auth->getSection();
    return (bool) preg_match('/\d+\s+N\.E\.\d*d\s+\d+/', $section)
      || (bool) preg_match('/No\.\s+\d+[A-Z]-[A-Z]+-\d+/', $section);
  }

  /**
   * {@inheritdoc}
   */
  public static function getPattern() : string {
    return 'e.g. 23A-CR-00456 (case number or citation)';
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Authority $auth) : ?string {
    // @todo Implement once court decisions source structure is determined.
    // See: https://public.courts.in.gov/decisions
    // Alt: https://www.courtlistener.com/api/rest/v3/
    throw new AuthorityParseException("Indiana Appellate Decisions parser is not yet implemented. Source research is still in progress for public.courts.in.gov/decisions.");
  }

}
