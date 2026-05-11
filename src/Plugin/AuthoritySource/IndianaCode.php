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
use Symfony\Component\DomCrawler\Crawler;

/**
 * Indiana Code Authority Source.
 *
 * Parses Indiana Code sections from static HTML files served by IGA.
 * URL pattern: https://iga.in.gov/ic/{year}/Title_{N}.html
 * Sections are identified by <div class="section" id="{citation}"> elements.
 */
#[AuthoritySource(
  id: 'laci_indiana:code',
  rootName: 'laci_indiana_code',
  label: 'Indiana Code'
)]
class IndianaCode extends AuthoritySourceBase {

  private const BASE_URL = 'https://iga.in.gov/ic';
  private const SECTION_RE = '/^\d+(-\d+(\.\d+)?){2,3}(-\d+(\.\d+)?)?$/m';

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
    return '[:digit:]+(-[:digit:]+(\\.[:digit:]+)?){2,3}(-[:digit:]+(\\.[:digit:]+)?)?';
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Authority $auth) : ?string {
    $section = $auth->getSection();
    $title = $this->extractTitle($section);
    $year = $this->getYear();

    $url = self::BASE_URL . "/{$year}/Title_{$title}.html";
    $html = $this->getWebData($url, [
      'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
      ],
    ]);

    return $this->extractSection($html, $section);
  }

  /**
   * Extracts the title number from a section citation.
   *
   * IC 31-16-2-6 → 31
   * IC 31-9-2-0.5 → 31
   */
  private function extractTitle(string $section) : string {
    $parts = explode('-', $section);
    return $parts[0];
  }

  /**
   * Returns the year to use for the IC URL.
   */
  private function getYear() : string {
    $year = \Drupal::config('laci_indiana.settings')->get('ic_year');
    return $year ?: '2025';
  }

  /**
   * Extracts the text of a specific section from the title HTML.
   *
   * The HTML structure is:
   * <div class="section" id="31-16-2-6">
   *   <span id="ic_number">IC 31-16-2-6</span>
   *   <span id="shortdescription">Section title</span>
   * </div>
   * <p>...section body...</p>
   * <p>...more body...</p>
   * <p style="margin-left: 0.185in;"> </p>  <!-- spacer -->
   * <div class="section" id="31-16-2-7">  <!-- next section -->
   */
  private function extractSection(string $html, string $sectionId) : string {
    $crawler = new Crawler($html);

    // Find the section div by its id.
    $sectionDiv = $crawler->filterXPath("//div[contains(@class, 'section') and @id='{$sectionId}']");

    if ($sectionDiv->count() === 0) {
      throw new AuthorityParseException("Section {$sectionId} not found in title HTML.");
    }

    // Collect all following siblings until the next structural div.
    $result = '';
    $node = $sectionDiv->getNode(0);

    // Include the section heading itself.
    $result .= $this->getInnerHtml($node);

    // Walk forward through siblings.
    while ($node = $node->nextSibling) {
      if ($node->nodeType !== XML_ELEMENT_NODE) {
        continue;
      }

      // Stop at the next structural element (section, chapter, article, title).
      if ($node->nodeName === 'div' && preg_match('/\b(section|chapter|article|title)\b/', $node->getAttribute('class'))) {
        break;
      }

      $result .= $this->getInnerHtml($node);
    }

    if (empty(trim(strip_tags($result)))) {
      throw new AuthorityParseException("Section {$sectionId} appears to be empty.");
    }

    return $result;
  }

  /**
   * Gets the inner HTML of a DOMNode.
   */
  private function getInnerHtml(\DOMNode $node) : string {
    $doc = new \DOMDocument();
    $doc->appendChild($doc->importNode($node, TRUE));
    return $doc->saveHTML();
  }

}
