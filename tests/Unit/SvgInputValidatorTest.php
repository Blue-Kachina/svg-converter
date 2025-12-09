<?php

namespace Tests\Unit;

use App\Services\Svg\SvgInputValidator;
use Tests\TestCase;
use Tests\Support\InteractsWithSvg;

class SvgInputValidatorTest extends TestCase
{
    use InteractsWithSvg;

    public function test_valid_minimal_svg_passes_validation_and_sanitization_keeps_shape(): void
    {
        $svg = $this->makeSvg(100, 100, '#ff0000');

        $validator = new SvgInputValidator();
        $result = $validator->validate($svg);

        $this->assertTrue($result['valid'], 'Expected valid minimal SVG');
        $this->assertSame([], $result['errors']);
        $this->assertNotEmpty($result['sanitized']);
        $this->assertStringContainsString('<rect', $result['sanitized']);
        $this->assertStringNotContainsString('<script', $result['sanitized']);
    }

    public function test_empty_input_is_invalid(): void
    {
        $validator = new SvgInputValidator();
        $result = $validator->validate('');

        $this->assertFalse($result['valid']);
        $this->assertContains('SVG content is empty', $result['errors']);
    }

    public function test_exceeds_max_bytes_is_reported(): void
    {
        $svg = $this->makeSvg(100, 100);
        $validator = new SvgInputValidator();
        // Set a tiny limit to force error
        $result = $validator->validate($svg, ['max_bytes' => 10]);

        $this->assertFalse($result['valid']);
        $this->assertContains('SVG exceeds maximum allowed size', $result['errors']);
    }

    public function test_width_height_limits_are_enforced(): void
    {
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="10000" height="9000"></svg>
        SVG;
        $validator = new SvgInputValidator();
        $result = $validator->validate($svg, ['max_width' => 2000, 'max_height' => 2000]);

        $this->assertFalse($result['valid']);
        $this->assertContains('SVG width exceeds limit', $result['errors']);
        $this->assertContains('SVG height exceeds limit', $result['errors']);
    }

    public function test_malicious_svg_is_detected_and_sanitized(): void
    {
        $svg = $this->makeMaliciousSvg();
        $validator = new SvgInputValidator();
        $result = $validator->validate($svg);

        $this->assertFalse($result['valid'], 'Malicious SVG should not be valid');
        // Expect detection of script and foreignObject
        $this->assertTrue($this->containsStringMatching($result['errors'], 'Disallowed element: <script>'));
        $this->assertTrue($this->containsStringMatching($result['errors'], 'Disallowed element: <foreignobject>'));

        $sanitized = $result['sanitized'];
        $this->assertNotEmpty($sanitized);
        $this->assertStringNotContainsString('<script', strtolower($sanitized));
        $this->assertStringNotContainsString('<foreignobject', strtolower($sanitized));
        $this->assertStringNotContainsString('javascript:', strtolower($sanitized));
    }

    public function test_remote_urls_are_blocked_when_disallowed(): void
    {
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
            <image href="https://example.com/x.png" />
        </svg>
        SVG;
        $validator = new SvgInputValidator();
        $result = $validator->validate($svg, ['allow_remote_refs' => false]);

        $this->assertFalse($result['valid']);
        $this->assertContains('Disallowed element: <image>', $result['errors']);
        $this->assertStringNotContainsString('<image', strtolower($result['sanitized']));
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
