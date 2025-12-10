<?php

namespace Tests\Unit;

use App\Services\Svg\SvgInputValidator;
use Tests\TestCase;
use Tests\Support\InteractsWithSvg;

class SvgInputValidatorSanitizeTest extends TestCase
{
    use InteractsWithSvg;

    public function test_event_handler_attributes_are_removed_during_sanitization(): void
    {
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
            <rect width="100" height="100" fill="#0f0" onclick="alert('x')" />
        </svg>
        SVG;

        $validator = new SvgInputValidator();
        $result = $validator->validate($svg);

        $this->assertTrue($result['valid'], 'SVG without other issues should be valid after sanitization');
        $this->assertStringNotContainsString('onclick=', strtolower($result['sanitized']));
    }

    public function test_javascript_href_is_removed_and_flagged(): void
    {
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
            <a href="javascript:alert('x')"><text x="10" y="20">x</text></a>
        </svg>
        SVG;

        $validator = new SvgInputValidator();
        $result = $validator->validate($svg);

        $this->assertFalse($result['valid'], 'Should be flagged due to javascript: URL');
        $this->assertTrue($this->containsStringMatching($result['errors'], 'JavaScript URLs are not allowed'));
        $this->assertStringNotContainsString('href="javascript:', strtolower($result['sanitized']));
    }

    public function test_width_decimal_values_are_ceiled_and_compared_against_limits(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="2000.4" height="100"></svg>';
        $validator = new SvgInputValidator();
        $result = $validator->validate($svg, ['max_width' => 2000]);

        $this->assertFalse($result['valid']);
        $this->assertContains('SVG width exceeds limit', $result['errors']);
    }

    private function containsStringMatching(array $errors, string $needle): bool
    {
        foreach ($errors as $e) {
            if (stripos($e, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
