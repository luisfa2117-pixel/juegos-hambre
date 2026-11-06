<?php

namespace Tests\Unit;

use App\Support\TextHighlighter;
use Tests\TestCase;

class TextHighlighterTest extends TestCase
{
    public function test_wraps_names_in_bold(): void
    {
        $html = TextHighlighter::names('Dean disparó a Avery', ['Dean', 'Avery'])->toHtml();

        $this->assertStringContainsString('<strong class="font-semibold text-ash">Dean</strong>', $html);
        $this->assertStringContainsString('<strong class="font-semibold text-ash">Avery</strong>', $html);
    }

    public function test_escapes_html_in_text_and_names(): void
    {
        $html = TextHighlighter::names('<script>alert(1)</script> huyó', ['<script>alert(1)</script>'])->toHtml();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_longer_names_win_over_shorter_ones(): void
    {
        $html = TextHighlighter::names('Avery Jr encontró un botiquín', ['Avery', 'Avery Jr'])->toHtml();

        $this->assertStringContainsString('<strong class="font-semibold text-ash">Avery Jr</strong>', $html);
    }
}
