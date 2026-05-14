<?php

namespace Drupal\laci_indiana\Plugin\AuthoritySource;

use Drupal\laci_core\Attribute\AuthoritySource;
use Drupal\laci_core\AuthoritySource\AuthoritySourceBase;
use Drupal\laci_core\Entities\Authority;
use Drupal\laci_core\Exception\AuthorityParseException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Generic HTML Authority Source for testing.
 *
 * Fetches any URL and extracts content by CSS selector.
 * The monitoring URL and selector are set on the authority node.
 * Section format: any string (used as display label only).
 */
#[AuthoritySource(
  id: 'laci_indiana:generic_html',
  rootName: 'laci_generic_html_(testing)',
  label: 'Generic HTML (Testing)'
)]
class GenericHtml extends AuthoritySourceBase {

  public function isValid(Authority $auth) : bool {
    return !empty($auth->getSection());
  }

  public static function getPattern() : string {
    return '.*';
  }

  public static function getSectionLabel() : string {
    return 'Section';
  }

  public function parse(Authority $auth) : ?string {
    $node = $auth->getNode();
    $url = $node->get('field_monitoringurl')->value;
    $selector = $auth->getSelector();

    if (empty($url)) {
      throw new AuthorityParseException("No monitoring URL set for this authority.");
    }

    $html = $this->getWebData($url, [
      'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
      ],
    ]);

    if (empty($selector)) {
      // No selector: return the whole body
      $crawler = new Crawler($html);
      $body = $crawler->filter('body');
      return $body->count() > 0 ? $body->html() : $html;
    }

    $crawler = new Crawler($html);
    $content = $crawler->filter($selector);
    if ($content->count() > 0) {
      return $content->html();
    }

    throw new AuthorityParseException("Selector '{$selector}' not found at {$url}");
  }

}
