<?php

namespace Drupal\Tests\tamper\Unit\Plugin\Tamper;

use Drupal\tamper\Plugin\Tamper\KeywordFilter;

/**
 * Tests the keyword filter plugin.
 *
 * @coversDefaultClass \Drupal\tamper\Plugin\Tamper\KeywordFilter
 * @group tamper
 */
class KeywordFilterTest extends TamperPluginTestBase {

  /**
   * {@inheritdoc}
   */
  protected function instantiatePlugin() {
    return new KeywordFilter([], 'keyword_filter', [], $this->getMockSourceDefinition());
  }

  /**
   * Tests applying the Tamper plugin with various settings.
   *
   * @dataProvider providerKeywordFilter
   */
  public function testKeywordFilter(string $expected, array $config) {
    $this->plugin = new KeywordFilter($config, 'keyword_filter', [], $this->getMockSourceDefinition());
    $this->assertEquals($expected, $this->plugin->tamper('This is a title'));
  }

  /**
   * Tests applying the Tamper plugin with the deprecated "words" setting.
   *
   * @dataProvider providerKeywordFilterBc
   */
  public function testKeywordFilterBc($expected, $config) {
    $this->plugin = new KeywordFilter($config, 'keyword_filter', [], $this->getMockSourceDefinition());
    $this->assertEquals($expected, $this->plugin->tamper('This is a title'));
  }

  /**
   * Data provider for testKeywordFilter().
   */
  public static function providerKeywordFilter(): array {
    $cases = static::providerKeywordFilterWithBcLayer();
    foreach ($cases as $key => $value) {
      unset($cases[$key]['config'][KeywordFilter::WORDS]);
    }
    return $cases;
  }

  /**
   * Data provider for testKeywordFilterBc().
   */
  public static function providerKeywordFilterBc(): array {
    $cases = static::providerKeywordFilterWithBcLayer();
    foreach ($cases as $key => $value) {
      unset($cases[$key]['config'][KeywordFilter::WORD_LIST]);
    }
    return $cases;
  }

  /**
   * Provides cases for testing keyword filter with BC layer.
   */
  public static function providerKeywordFilterWithBcLayer(): array {
    return [
      'StriPosFilter' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => 'booya',
          KeywordFilter::WORD_LIST => ['booya'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'StriPosPass' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'this',
          KeywordFilter::WORD_LIST => ['this'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'StrPosFilter' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => 'this',
          KeywordFilter::WORD_LIST => ['this'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => TRUE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'StrPosPass' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'This',
          KeywordFilter::WORD_LIST => ['This'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => TRUE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'StrPosFilterMultipleWords' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => "this\nTitle",
          KeywordFilter::WORD_LIST => ['this', 'Title'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => TRUE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      // Only one words needs a match.
      'StrPosPassMultipleWords' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => "World\nThis\ntitle",
          KeywordFilter::WORD_LIST => ['World', 'This', 'title'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => TRUE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'ExactFilter' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => 'a title',
          KeywordFilter::WORD_LIST => ['a title'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => TRUE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'ExactFilter2' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => 'This is  a title',
          KeywordFilter::WORD_LIST => ['This is  a title'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => TRUE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'ExactCaseInsensitivePass' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'This is a Title',
          KeywordFilter::WORD_LIST => ['This is a Title'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => TRUE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'ExactCaseSensitiveFilter' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => 'This is a Title',
          KeywordFilter::WORD_LIST => ['This is a Title'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => TRUE,
          KeywordFilter::CASE_SENSITIVE => TRUE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'ExactCaseSensitivePass' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'This is a title',
          KeywordFilter::WORD_LIST => ['This is a title'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => TRUE,
          KeywordFilter::CASE_SENSITIVE => TRUE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'WordBoundariesFilter' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => 'tit',
          KeywordFilter::WORD_LIST => ['tit'],
          KeywordFilter::WORD_BOUNDARIES => TRUE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'WordBoundariesPass' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'title',
          KeywordFilter::WORD_LIST => ['title'],
          KeywordFilter::WORD_BOUNDARIES => TRUE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'WordBoundariesPassMultipleWords' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => "tit\ntitle\nthis",
          KeywordFilter::WORD_LIST => ['tit', 'title', 'this'],
          KeywordFilter::WORD_BOUNDARIES => TRUE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => FALSE,
        ],
      ],
      'InvertEnablingResult' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'booya',
          KeywordFilter::WORD_LIST => ['booya'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => TRUE,
        ],
      ],
      'InvertFilteringResult' => [
        'expected' => '',
        'config' => [
          KeywordFilter::WORDS => 'this',
          KeywordFilter::WORD_LIST => ['this'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => TRUE,
        ],
      ],
      'InvertEnablingFailedCaseResult' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'this',
          KeywordFilter::WORD_LIST => ['this'],
          KeywordFilter::WORD_BOUNDARIES => FALSE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => TRUE,
          KeywordFilter::INVERT => TRUE,
        ],
      ],
      'InvertEnablingFailedExactResult' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'a  title',
          KeywordFilter::WORD_LIST => ['a  title'],
          KeywordFilter::WORD_BOUNDARIES => TRUE,
          KeywordFilter::EXACT => TRUE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => TRUE,
        ],
      ],
      'InvertFilteringPassedExactResult' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'This is  a title',
          KeywordFilter::WORD_LIST => ['This is  a title'],
          KeywordFilter::WORD_BOUNDARIES => TRUE,
          KeywordFilter::EXACT => TRUE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => TRUE,
        ],
      ],
      'InvertWordBoundariesFilter' => [
        'expected' => 'This is a title',
        'config' => [
          KeywordFilter::WORDS => 'tit',
          KeywordFilter::WORD_LIST => ['tit'],
          KeywordFilter::WORD_BOUNDARIES => TRUE,
          KeywordFilter::EXACT => FALSE,
          KeywordFilter::CASE_SENSITIVE => FALSE,
          KeywordFilter::INVERT => TRUE,
        ],
      ],
    ];
  }

  /**
   * @covers ::tamper
   * @dataProvider providerKeywordFilterWithArrayData
   */
  public function testKeywordFilterWithArrayData($expected, array $data) {
    $config = [
      KeywordFilter::WORD_LIST => ['Foo', 'Bar', 'Qux'],
    ];

    $this->plugin = new KeywordFilter($config, 'keyword_filter', [], $this->getMockSourceDefinition());
    $this->assertEquals($expected, $this->plugin->tamper($data));
  }

  /**
   * Data provider for testKeywordFilterWithArrayData().
   */
  public static function providerKeywordFilterWithArrayData() {
    return [
      'all keywords used' => [
        'expected' => [
          'There is a Foo around here',
          'Drink something at the bar',
          'Lorem ipsum',
          'It is a quxxie thing.',
        ],
        'data' => [
          'There is a Foo around here',
          'Drink something at the bar',
          'Lorem ipsum',
          'It is a quxxie thing.',
        ],
      ],
      'only one keyword used' => [
        'expected' => [
          'There is a fool around here',
        ],
        'data' => [
          'There is a fool around here',
        ],
      ],
      'no keywords used' => [
        'expected' => '',
        'data' => [
          'Lorem ipsum',
        ],
      ],
    ];
  }

  /**
   * @covers ::getRegex
   */
  public function testGetRegex() {
    $plugin = new KeyWordFilterWrapper([], 'keyword_filter', [], $this->getMockSourceDefinition());
    $this->assertEquals('/FooBar/ui', $plugin->getRegexWrapper('FooBar'));
  }

  /**
   * @covers ::getRegex
   */
  public function testGetRegexInvalidWordBoundaries() {
    $config = [
      KeywordFilter::WORD_BOUNDARIES => TRUE,
    ];
    $plugin = new KeyWordFilterWrapper($config, 'keyword_filter', [], $this->getMockSourceDefinition());
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Search text must begin and end with a letter, number, or underscore when word boundaries should be respected.');
    $plugin->getRegexWrapper('*Bar');
  }

}

/**
 * Wrapper for KeyWordFilter to test protected methods.
 */
class KeyWordFilterWrapper extends KeywordFilter {

  /**
   * Wrapper for KeyWordFilter::getRegex().
   *
   * @param string $word
   *   The word to create a regex for.
   *
   * @return string
   *   The regular expression.
   *
   * @throws \RuntimeException
   *   In case the word could not be converted to a regular expression.
   */
  public function getRegexWrapper(string $word): string {
    return $this->getRegex($word);
  }

}
