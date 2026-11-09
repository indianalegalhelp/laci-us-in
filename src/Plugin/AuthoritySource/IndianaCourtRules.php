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
use Drupal\laci_core\Exception\AuthorityConfigException;
use Drupal\laci_core\Exception\AuthorityParseException;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Indiana Court Rules: one parser for every rule set on rules.incourts.gov.
 *
 * Every rule set lives under https://rules.incourts.gov/Content/{set}/ with a
 * table of contents at default.htm and one page per piece at
 * {set}/{piece}/current.htm. Pieces are not always "rule{N}": some sets use
 * section{N}, guideline{N}{a}, Roman numerals, or named pages (preamble,
 * scope, appendix...). So this parser never builds a piece URL from the
 * section number; it reads the set's table of contents and looks it up.
 *
 * Which set an Authority belongs to comes from its type term: a child of the
 * "Indiana Court Rules" parent term. The child's description holds the set's
 * URL (https://rules.incourts.gov/Content/{set}/), so renaming a term does not
 * break it. Adding a set = adding a child term; no code change.
 *
 * A sub-part in the section (e.g. "12(B)") is accepted and kept in the
 * citation, but the whole piece (Rule 12) is monitored: a false alarm costs a
 * reviewer a minute; a missed change is what LACI exists to prevent.
 */
#[AuthoritySource(
  id: 'laci_indiana:court_rules',
  rootName: 'laci_indiana_court_rules',
  label: 'Indiana Court Rules'
)]
class IndianaCourtRules extends AuthoritySourceBase {

  private const BASE_URL = 'https://rules.incourts.gov/Content/';

  /**
   * Allowed characters in a section: "56", "4.1", "3A", "IV", "Appendix A",
   * optionally followed by sub-parts like "(B)(1)".
   */
  private const SECTION_RE = '/^[A-Za-z0-9][A-Za-z0-9 .\-]*(\([A-Za-z0-9]+\))*$/';

  private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

  /**
   * {@inheritdoc}
   */
  public static function getPattern() : string {
    return 'e.g. 56, 4.1, 3A, IV, or Preamble. A sub-part like 12(B) is allowed; the whole rule is monitored.';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSectionLabel() : string {
    return 'Rule';
  }

  /**
   * {@inheritdoc}
   *
   * Format check always; table-of-contents check when the site answers. If
   * the site is unreachable at save time we accept the format and let the
   * next parse report the problem loudly, rather than blocking the editor.
   */
  public function isValid(Authority $auth) : bool {
    $section = trim($auth->getSection() ?? '');
    if ($section === '' || !preg_match(self::SECTION_RE, $section)) {
      return FALSE;
    }
    try {
      $set = $this->getSetSlug($auth);
      return $this->findPiece($set, $section) !== NULL;
    }
    catch (AuthorityConfigException $e) {
      // Wrong term (e.g. the parent itself): not valid.
      return FALSE;
    }
    catch (\Exception $e) {
      // Site unreachable: don't block the save on the format-valid input.
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Authority $auth) : ?string {
    $set = $this->getSetSlug($auth);
    $section = trim($auth->getSection());

    $piece = $this->findPiece($set, $section);
    if ($piece === NULL) {
      throw new AuthorityParseException("\"{$section}\" is not in the table of contents for " . self::BASE_URL . "{$set}/default.htm");
    }

    $url = self::BASE_URL . "{$set}/{$piece}/current.htm";
    $html = $this->fetch($url);

    $crawler = new Crawler($html);
    $content = $crawler->filter('#mc-main-content');
    if ($content->count() === 0) {
      // No fallback to <body>: diffing navigation/footer churn would send
      // false "this law changed" alerts. Fail loudly instead.
      throw new AuthorityParseException("No #mc-main-content on {$url}; the site layout may have changed.");
    }
    return $content->html();
  }

  /**
   * Resolves the rule-set slug ("trial", "child-support"...) for an Authority.
   */
  private function getSetSlug(Authority $auth) : string {
    $term = $auth->getAuthType();
    if (empty($term->parent->entity)) {
      throw new AuthorityConfigException('Pick a specific rule set under "Indiana Court Rules", not the parent itself.');
    }
    $slug = self::slugFromTerm($term);
    if ($slug === NULL) {
      throw new AuthorityConfigException("The type \"{$term->label()}\" has no rules.incourts.gov URL in its description (expected " . self::BASE_URL . "{set}/).");
    }
    return $slug;
  }

  /**
   * Reads the set slug from a term's description.
   *
   * Public and static so the update hook can use the same rule.
   */
  public static function slugFromTerm(Term $term) : ?string {
    $desc = (string) ($term->getDescription() ?? '');
    if (preg_match('#rules\.incourts\.gov/Content/([a-z0-9-]+)/?#i', $desc, $m)) {
      return strtolower($m[1]);
    }
    return NULL;
  }

  /**
   * Finds the piece slug ("rule4-1", "guideline3a", "preamble"...) for a section.
   */
  private function findPiece(string $set, string $section) : ?string {
    $toc = $this->getToc($set);
    return $toc[self::normalize($section)] ?? NULL;
  }

  /**
   * Returns [normalized key => piece slug] from the set's table of contents.
   */
  private function getToc(string $set) : array {
    $html = $this->fetch(self::BASE_URL . "{$set}/default.htm");
    $crawler = new Crawler($html);
    $map = [];
    $crawler->filter('#mc-main-content a[href]')->each(function (Crawler $a) use (&$map) {
      $href = $a->attr('href');
      // Only this set's own pieces: "{piece}/current.htm" (relative, no "../").
      if (preg_match('#^([A-Za-z0-9-]+)/current\.htm(?:\#.*)?$#', $href, $m)) {
        $piece = $m[1];
        $map[self::normalize(self::pieceToSection($piece))] = $piece;
      }
    });
    if (!$map) {
      throw new AuthorityParseException("Found no rules in the table of contents at " . self::BASE_URL . "{$set}/default.htm; the site layout may have changed.");
    }
    return $map;
  }

  /**
   * Turns a piece slug into the section a person would type.
   *
   * rule56 → 56, rule4-1 → 4.1, ruleIV → IV, section3 → 3, guideline3a → 3a,
   * appendix-a → appendix a, preamble → preamble, rule → rule.
   */
  private static function pieceToSection(string $piece) : string {
    if (preg_match('/^(?:rule|section|guideline)(\d.*|[ivxlc]+)$/i', $piece, $m)) {
      return $m[1];
    }
    return $piece;
  }

  /**
   * Normalizes typed sections and piece-derived sections to the same key.
   *
   * Drops a leading "Rule"/"Section"/"Guideline", drops sub-parts "(B)(1)",
   * lowercases, and treats spaces, dashes and dots alike: "Rule 4.1",
   * "4-1" and "4.1" all become "4.1"; "Appendix A" and "appendix-a" become
   * "appendix.a".
   */
  public static function normalize(string $s) : string {
    $s = strtolower(trim($s));
    $s = preg_replace('/^(?:rule|section|guideline)s?\s*(?=[0-9]|[ivxlc]+\b)/', '', $s);
    $s = preg_replace('/\(.*$/', '', $s);
    $s = preg_replace('/[\s.\-]+/', '.', trim($s));
    return trim($s, '.');
  }

  /**
   * Fetches a page (cached by the base class for ~23h).
   */
  private function fetch(string $url) : string {
    return $this->getWebData($url, [
      'headers' => ['User-Agent' => self::USER_AGENT],
    ]);
  }

}
