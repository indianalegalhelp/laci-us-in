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

use Drupal\laci_core\AuthoritySource\AuthoritySourceBase;
use Drupal\laci_core\Entities\Authority;
use Drupal\laci_core\Exception\AuthorityParseException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base class for Indiana Court Rule Authority Sources.
 *
 * All court rules on rules.incourts.gov follow the same URL pattern:
 * https://rules.incourts.gov/Content/{category}/rule{number}/current.htm
 *
 * Subclasses only need to override getCategory() and the plugin attributes.
 */
abstract class IndianaCourtRuleBase extends AuthoritySourceBase {

  private const BASE_URL = 'https://rules.incourts.gov/Content';
  private const SECTION_RE = '/^\d+(\.\d+)?(-\d+)?$/m';

  /**
   * Returns the category path segment for this court rule type.
   *
   * E.g., 'trial', 'evidence', 'appellate', 'criminal', etc.
   */
  abstract protected static function getCategory() : string;

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
    return '[:digit:]+(\\.[:digit:]+)?(-[:digit:]+)?';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSectionLabel() : string {
    return 'Rule';
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Authority $auth) : ?string {
    $section = $auth->getSection();
    $ruleSlug = $this->sectionToSlug($section);
    $url = self::BASE_URL . '/' . static::getCategory() . "/rule{$ruleSlug}/current.htm";

    $html = $this->getWebData($url);
    return $this->extractRuleText($html);
  }

  /**
   * Converts a section number to the URL slug format.
   *
   * Rule 4.1 → "4-1"
   * Rule 9.2 → "9-2"
   * Rule 401 → "401"
   * Rule 12  → "12"
   */
  private function sectionToSlug(string $section) : string {
    return str_replace('.', '-', $section);
  }

  /**
   * Extracts the rule text from the HTML page.
   */
  private function extractRuleText(string $html) : string {
    $crawler = new Crawler($html);

    // The rule content is in the main body of the page.
    // Try to find the rule content area.
    $content = $crawler->filter('main, .content, #content, article, .rule-content');

    if ($content->count() > 0) {
      return $content->html();
    }

    // Fallback: use the body content, excluding navigation/header/footer.
    $body = $crawler->filter('body');
    if ($body->count() > 0) {
      return $body->html();
    }

    throw new AuthorityParseException("Could not extract rule text from page.");
  }

}
