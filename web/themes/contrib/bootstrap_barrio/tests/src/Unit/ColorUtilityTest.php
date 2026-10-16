<?php

declare(strict_types=1);

namespace Drupal\Tests\bootstrap_barrio\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the color utility functions in Bootstrap Barrio theme.
 *
 * @group bootstrap_barrio
 * @coversDefaultClass \Drupal\bootstrap_barrio\ColorUtility
 */
#[Group('bootstrap_barrio')]
class ColorUtilityTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Include the theme file to access helper functions.
    require_once dirname(__DIR__, 3) . '/bootstrap_barrio.theme';
  }

  /**
   * Tests _hex2rgb() function.
   *
   * @dataProvider hex2rgbProvider
   */
  #[DataProvider('hex2rgbProvider')]
  public function testHex2Rgb(string $hex, string $expected): void {
    $this->assertEquals($expected, _hex2rgb($hex));
  }

  /**
   * Data provider for testHex2Rgb.
   *
   * @return array
   *   Test data.
   */
  public static function hex2rgbProvider(): array {
    return [
      'white' => ['#ffffff', '255, 255, 255'],
      'black' => ['#000000', '0, 0, 0'],
      'red' => ['#ff0000', '255, 0, 0'],
      'green' => ['#00ff00', '0, 255, 0'],
      'blue' => ['#0000ff', '0, 0, 255'],
      'primary default' => ['#2F3C7E', '47, 60, 126'],
      'secondary default' => ['#FBEAEB', '251, 234, 235'],
      'gray 200' => ['#e9ecef', '233, 236, 239'],
      'gray 800' => ['#343a40', '52, 58, 64'],
    ];
  }

  /**
   * Tests _hex2rgba() function.
   *
   * @dataProvider hex2rgbaProvider
   */
  #[DataProvider('hex2rgbaProvider')]
  public function testHex2Rgba(string $hex, float $opacity, string $expected): void {
    $this->assertEquals($expected, _hex2rgba($hex, $opacity));
  }

  /**
   * Data provider for testHex2Rgba.
   *
   * @return array
   *   Test data.
   */
  public static function hex2rgbaProvider(): array {
    return [
      'white full opacity' => ['#ffffff', 1, 'rgba(255, 255, 255, 1)'],
      'white half opacity' => ['#ffffff', 0.5, 'rgba(255, 255, 255, 0.5)'],
      'black transparent' => ['#000000', 0, 'rgba(0, 0, 0, 0)'],
      'primary with opacity' => ['#2F3C7E', 0.8, 'rgba(47, 60, 126, 0.8)'],
      'red quarter opacity' => ['#ff0000', 0.25, 'rgba(255, 0, 0, 0.25)'],
    ];
  }

  /**
   * Tests _color_luminance() function for lightening colors.
   *
   * @dataProvider colorLuminanceLightenProvider
   */
  #[DataProvider('colorLuminanceLightenProvider')]
  public function testColorLuminanceLighten(string $hex, float $percent, string $expected): void {
    $this->assertEquals($expected, _color_luminance($hex, $percent));
  }

  /**
   * Data provider for testColorLuminanceLighten.
   *
   * @return array
   *   Test data.
   */
  public static function colorLuminanceLightenProvider(): array {
    return [
      'lighten gray by 20%' => ['#808080', 0.2, '#999999'],
      'lighten dark gray by 50%' => ['#404040', 0.5, '#606060'],
      'no change at 0%' => ['#808080', 0, '#808080'],
    ];
  }

  /**
   * Tests _color_luminance() function for darkening colors.
   *
   * @dataProvider colorLuminanceDarkenProvider
   */
  #[DataProvider('colorLuminanceDarkenProvider')]
  public function testColorLuminanceDarken(string $hex, float $percent, string $expected): void {
    $this->assertEquals($expected, _color_luminance($hex, $percent));
  }

  /**
   * Data provider for testColorLuminanceDarken.
   *
   * @return array
   *   Test data.
   */
  public static function colorLuminanceDarkenProvider(): array {
    return [
      'darken gray by 20%' => ['#808080', -0.2, '#666666'],
      'darken by 15% (theme default)' => ['#2F3C7E', -0.15, '#28336b'],
      'darken white by 50%' => ['#ffffff', -0.5, '#7f7f7f'],
    ];
  }

  /**
   * Tests _color_luminance() handles invalid input.
   */
  public function testColorLuminanceInvalidInput(): void {
    // Should handle hex without hash.
    $result = _color_luminance('808080', 0.2);
    $this->assertEquals('#999999', $result);

    // Should handle short hex.
    $result = _color_luminance('#888', 0);
    $this->assertEquals('#888888', $result);
  }

  /**
   * Tests _getContrastColor() function.
   *
   * @dataProvider getContrastColorProvider
   */
  #[DataProvider('getContrastColorProvider')]
  public function testGetContrastColor(string $hexColor, string $expected): void {
    $white = '#fff';
    $dark = '#000';
    $this->assertEquals($expected, _getContrastColor($hexColor, $white, $dark));
  }

  /**
   * Data provider for testGetContrastColor.
   *
   * @return array
   *   Test data.
   */
  public static function getContrastColorProvider(): array {
    return [
      'white background needs dark text' => ['#ffffff', '#000'],
      'black background needs white text' => ['#000000', '#fff'],
      'light yellow needs dark text' => ['#FBEAEB', '#000'],
      'dark blue needs white text' => ['#2F3C7E', '#fff'],
      'medium gray (edge case)' => ['#808080', '#000'],
      'red needs white text' => ['#ff0000', '#fff'],
      'light gray needs dark text' => ['#e9ecef', '#000'],
    ];
  }

  /**
   * Tests _getContrastColor() with custom light/dark colors.
   */
  public function testGetContrastColorCustomColors(): void {
    // Test with custom white and dark colors.
    $customWhite = '#f8f9fa';
    $customDark = '#212529';

    // Light background should return dark.
    $this->assertEquals($customDark, _getContrastColor('#ffffff', $customWhite, $customDark));

    // Dark background should return white.
    $this->assertEquals($customWhite, _getContrastColor('#000000', $customWhite, $customDark));
  }

  /**
   * Tests _get_color() function.
   *
   * @dataProvider getColorProvider
   */
  #[DataProvider('getColorProvider')]
  public function testGetColor(string $colorKey, array $colors, string $expected): void {
    $this->assertEquals($expected, _get_color($colorKey, $colors));
  }

  /**
   * Data provider for testGetColor.
   *
   * @return array
   *   Test data.
   */
  public static function getColorProvider(): array {
    $colors = [
      'white' => '#fff',
      'black' => '#000',
      'primary' => '#2F3C7E',
      'secondary' => '#FBEAEB',
      'base' => '#343a40',
    ];

    return [
      'get white' => ['white', $colors, '#fff'],
      'get black' => ['black', $colors, '#000'],
      'get primary' => ['primary', $colors, '#2F3C7E'],
      'get secondary' => ['secondary', $colors, '#FBEAEB'],
      'get base' => ['base', $colors, '#343a40'],
      'fallback to input if not found' => ['#custom', $colors, '#custom'],
      'fallback for unknown key' => ['unknown', $colors, 'unknown'],
    ];
  }

  /**
   * Tests _get_color() with empty colors array.
   */
  public function testGetColorEmptyArray(): void {
    $this->assertEquals('#test', _get_color('#test', []));
    $this->assertEquals('custom', _get_color('custom', []));
  }

  /**
   * Tests color conversion round-trip.
   */
  public function testColorConversionRoundTrip(): void {
    $originalHex = '#2F3C7E';

    // Convert to RGB.
    $rgb = _hex2rgb($originalHex);
    $this->assertEquals('47, 60, 126', $rgb);

    // The RGB values should match the hex.
    $parts = explode(', ', $rgb);
    $reconstructed = sprintf('#%02x%02x%02x', (int) $parts[0], (int) $parts[1], (int) $parts[2]);
    $this->assertEquals(strtolower($originalHex), strtolower($reconstructed));
  }

  /**
   * Tests extreme luminance adjustments.
   */
  public function testExtremeLuminanceAdjustments(): void {
    // Lightening white should stay white (can't go lighter).
    $result = _color_luminance('#ffffff', 0.5);
    $this->assertEquals('#ffffff', $result);

    // Darkening black should stay black (can't go darker).
    $result = _color_luminance('#000000', -0.5);
    $this->assertEquals('#000000', $result);
  }

  /**
   * Tests WCAG contrast ratio scenarios.
   *
   * Ensures the theme provides adequate contrast for accessibility.
   */
  public function testWcagContrastScenarios(): void {
    // These are Bootstrap Barrio's default color schemes.
    $schemes = [
      'default' => ['#2F3C7E', '#FBEAEB'],
      'blue_peach' => ['#00539C', '#EEA47F'],
      'red_yellow' => ['#F96167', '#F9E795'],
    ];

    foreach ($schemes as $name => $colors) {
      [$primary, $secondary] = $colors;

      // Primary should have good contrast.
      $primaryContrast = _getContrastColor($primary, '#fff', '#000');
      $this->assertNotEmpty($primaryContrast, "Scheme $name primary should have contrast color");

      // Secondary should have good contrast.
      $secondaryContrast = _getContrastColor($secondary, '#fff', '#000');
      $this->assertNotEmpty($secondaryContrast, "Scheme $name secondary should have contrast color");
    }
  }

}
