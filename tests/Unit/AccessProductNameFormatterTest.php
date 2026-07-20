<?php

namespace Tests\Unit;

use App\Services\AccessProductNameFormatter;
use PHPUnit\Framework\TestCase;

class AccessProductNameFormatterTest extends TestCase
{
    public function test_it_combines_the_main_name_and_a_bracketed_variant_without_a_space(): void
    {
        $names = (new AccessProductNameFormatter)->format([
            '商品名' => '<生>',
            '容量(ml)' => 720,
            '容量単位' => 'ml',
        ], [
            '主商品名' => '安芸虎夏純吟',
        ]);

        $this->assertSame('安芸虎夏純吟<生>', $names['name']);
        $this->assertSame('安芸虎夏純吟<生> 720ml', $names['display_name']);
    }

    public function test_it_uses_a_space_for_an_unbracketed_variant_and_avoids_duplicates(): void
    {
        $formatter = new AccessProductNameFormatter;

        $this->assertSame('安芸虎 純米吟醸 生', $formatter->composeName('安芸虎 純米吟醸', '生'));
        $this->assertSame('安芸虎 純米吟醸', $formatter->composeName('安芸虎 純米吟醸', '安芸虎 純米吟醸'));
    }
}
