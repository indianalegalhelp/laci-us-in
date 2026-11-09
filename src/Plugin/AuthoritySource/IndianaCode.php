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
 *
 * The edition year is picked automatically (current year, else previous
 * year) unless the "Indiana Code year" setting pins one. A section that
 * disappears from a new edition is reported as likely repealed/renumbered.
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
    return 'e.g. 31-16-2-6 (format: Title-Article-Chapter-Section)';
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Authority $auth) : ?string {
    $section = $auth->getSection();
    $title = $this->extractTitle($section);
    [$year, $html] = $this->loadTitle($title);

    if (!$this->hasSection($html, $section)) {
      $this->throwMissingSection($title, $section, $year, $html);
    }

    return $this->extractSection($html, $section);
  }

  /**
   * Loads a title from the right edition and returns [year, html].
   *
   * The "Indiana Code year" setting is "auto" (default) or a pinned year.
   * Auto tries the current year's edition, then the previous year's. IGA
   * usually posts a new edition in late summer, so for part of each year the
   * previous edition is the current law.
   *
   * IGA never answers 404: a missing year or title comes back as HTTP 200
   * with the site's JavaScript app shell. So "published" is decided by the
   * content (real section markup), not the status code. Real network
   * failures still throw, so LACI logs them and keeps the last snapshot.
   */
  private function loadTitle(string $title) : array {
    $setting = trim((string) \Drupal::config('laci_indiana.settings')->get('ic_year'));

    if (preg_match('/^\d{4}$/', $setting)) {
      $html = $this->fetchTitleHtml($this->titleUrl($setting, $title));
      if (!self::isPublishedEdition($html)) {
        throw new AuthorityParseException("The {$setting} edition of Indiana Code Title {$title} is not published at " . $this->titleUrl($setting, $title) . ". The Indiana Code year setting is pinned to {$setting}; set it to \"auto\" or an earlier year.");
      }
      return [$setting, $html];
    }

    $current = (int) date('Y');
    foreach ([$current, $current - 1] as $year) {
      $html = $this->fetchTitleHtml($this->titleUrl((string) $year, $title));
      if (self::isPublishedEdition($html)) {
        return [(string) $year, $html];
      }
    }
    throw new AuthorityParseException("No published edition of Indiana Code Title {$title} found for {$current} or " . ($current - 1) . ".");
  }

  /**
   * True when the page is a real Indiana Code title, not IGA's app shell.
   */
  public static function isPublishedEdition(string $html) : bool {
    return str_contains($html, 'class="section"');
  }

  /**
   * Builds the IGA URL for a title in a given edition year.
   */
  private function titleUrl(string $year, string $title) : string {
    return self::BASE_URL . "/{$year}/Title_{$title}.html";
  }

  /**
   * True when the title HTML contains the section.
   */
  private function hasSection(string $html, string $sectionId) : bool {
    $crawler = new Crawler($html);
    return $crawler->filterXPath("//div[contains(@class, 'section') and @id='{$sectionId}']")->count() > 0;
  }

  /**
   * Throws a specific error for a section missing from this edition.
   *
   * A section that existed last year and is gone now was most likely
   * repealed or renumbered: the biggest kind of change, which must not look
   * like an ordinary fetch error. Say so, and quote the chapter's own note
   * (e.g. "repealed by P.L.145-2026") when IGA has one.
   */
  private function throwMissingSection(string $title, string $section, string $year, string $html) : void {
    $message = "IC {$section} is not in the {$year} edition of Title {$title}.";

    $previous = (string) ((int) $year - 1);
    try {
      $old = $this->fetchTitleHtml($this->titleUrl($previous, $title));
      if (self::isPublishedEdition($old) && $this->hasSection($old, $section)) {
        $message .= " It was in the {$previous} edition, so it was most likely REPEALED OR RENUMBERED. Review the documents that cite it.";
      }
      else {
        $message .= " It was not in the {$previous} edition either; check the section number.";
      }
    }
    catch (\Exception $e) {
      // The comparison is a courtesy; the missing section is the real news.
    }

    $note = self::chapterNote($html, $section);
    if ($note !== '') {
      $message .= " Chapter note: \"{$note}\"";
    }

    throw new AuthorityParseException($message);
  }

  /**
   * Returns the chapter heading plus its first note, if IGA printed one.
   *
   * IC 31-9-2-16 → chapter div id "31-9-2" → "IC 31-9-2 Chapter 2. Repealed
   * Note: This version of chapter repealed by P.L.145-2026, SEC.160. ..."
   */
  public static function chapterNote(string $html, string $section) : string {
    $chapterId = implode('-', array_slice(explode('-', $section), 0, 3));
    $crawler = new Crawler($html);
    $chapter = $crawler->filterXPath("//div[contains(@class, 'chapter') and @id='{$chapterId}']");
    if ($chapter->count() === 0) {
      return '';
    }
    $node = $chapter->getNode(0);
    $text = $node->textContent;
    $next = $node->nextSibling;
    while ($next && $next->nodeType !== XML_ELEMENT_NODE) {
      $next = $next->nextSibling;
    }
    if ($next && !($next->nodeName === 'div' && preg_match('/\b(section|chapter|article|title)\b/', $next->getAttribute('class')))) {
      $text .= ' ' . $next->textContent;
    }
    $text = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '…' : $text;
  }

  /**
   * Fetches title HTML with Accept-Encoding to satisfy CloudFront,
   * then decompresses the response.
   */
  private function fetchTitleHtml(string $url) : string {
    // Titles are large (Title 31 is ~6 MB) and one cron run checks many
    // sections of the same title. Keep the last few pages for this request.
    if (isset(self::$pages[$url])) {
      return self::$pages[$url];
    }
    $html = $this->downloadTitleHtml($url);
    self::$pages[$url] = $html;
    if (count(self::$pages) > 4) {
      array_shift(self::$pages);
    }
    return $html;
  }

  /**
   * Pages fetched during this request, keyed by URL.
   */
  private static array $pages = [];

  /**
   * Downloads one title page (no memo).
   */
  private function downloadTitleHtml(string $url) : string {
    $context = stream_context_create([
      'http' => [
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36\r\nAccept-Encoding: gzip, deflate\r\n",
        'timeout' => 30,
      ],
      'ssl' => [
        'verify_peer' => TRUE,
      ],
    ]);

    $compressed = file_get_contents($url, FALSE, $context);
    if ($compressed === FALSE) {
      throw new AuthorityParseException("Failed to fetch $url");
    }

    $html = @gzdecode($compressed);
    if ($html === FALSE) {
      $html = $compressed;
    }

    return $html;
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
