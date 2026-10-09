<?php

namespace Tests\Unit;

use App\Services\Report\ReportTextBlocks;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مقاطع النص المنسّقة — عقدٌ مع الجوال: أنواع المقاطع وترتيبها.
 * أي كسر هنا يعني نصاً يظهر فوضوياً على الشاشة.
 */
class ReportTextBlocksTest extends TestCase
{
    #[Test]
    public function empty_text_yields_no_blocks(): void
    {
        $this->assertSame([], ReportTextBlocks::build(null));
        $this->assertSame([], ReportTextBlocks::build('   '));
    }

    #[Test]
    public function multi_line_text_becomes_paragraphs_not_one_blob(): void
    {
        $blocks = ReportTextBlocks::build("السطر الأول\nالسطر الثاني\nالسطر الثالث");

        $this->assertCount(3, $blocks);
        $this->assertSame('paragraph', $blocks[0]['type']);
        $this->assertSame('السطر الأول', $blocks[0]['text']);
        $this->assertSame('السطر الثالث', $blocks[2]['text']);
    }

    #[Test]
    public function numbered_lines_become_ordered_items(): void
    {
        $blocks = ReportTextBlocks::build("1. تم إبلاغ الجهة المختصة\n2. متابعة ميدانية\n3. رفع تقرير");

        $this->assertCount(3, $blocks);
        foreach ($blocks as $i => $b) {
            $this->assertSame('item', $b['type']);
            $this->assertSame($i + 1, $b['order'], 'order must follow the written number');
        }
        $this->assertSame('تم إبلاغ الجهة المختصة', $blocks[0]['text']);
    }

    #[Test]
    public function bullets_get_sequential_numbers_instead_of_dashes(): void
    {
        $blocks = ReportTextBlocks::build("- صيانة الكاميرا\n- فحص التسجيل\n- تحديث النظام");

        $this->assertCount(3, $blocks);
        $this->assertSame('item', $blocks[0]['type']);
        $this->assertSame(1, $blocks[0]['order']);
        $this->assertSame('صيانة الكاميرا', $blocks[0]['text']);
    }

    #[Test]
    public function short_line_ending_with_colon_becomes_heading_without_colon(): void
    {
        $blocks = ReportTextBlocks::build("الملاحظات:\nتم رصد حركة غير اعتيادية أمام المدخل");

        $this->assertSame('heading', $blocks[0]['type']);
        $this->assertSame('الملاحظات', $blocks[0]['text'], 'trailing colon is stripped');
        $this->assertSame('paragraph', $blocks[1]['type']);
    }

    #[Test]
    public function long_sentence_ending_with_colon_stays_a_paragraph(): void
    {
        $long = 'هذا نص طويل جداً يمتدّ بعيداً عن ستين حرفاً بكثير وينتهي بنقطتين::';
        $blocks = ReportTextBlocks::build($long);

        $this->assertSame('paragraph', $blocks[0]['type']);
    }

    #[Test]
    public function recommendations_mode_uses_numbered_sequence_across_lines(): void
    {
        $blocks = ReportTextBlocks::build(
            "1. First action taken\n2. Second action taken",
            'recommendations',
        );

        $this->assertCount(2, $blocks);
        $this->assertSame('item', $blocks[0]['type']);
        $this->assertSame('First action taken', $blocks[0]['text']);
    }

    #[Test]
    public function recommendations_without_numbering_fall_back_to_paragraphs(): void
    {
        $blocks = ReportTextBlocks::build(
            'أُخذت إجراءات فورية، وأُبلغت الجهات المعنية، وسُجلت الحالة.',
            'recommendations',
        );

        $this->assertNotEmpty($blocks);
        $this->assertSame('paragraph', $blocks[0]['type']);
    }

    #[Test]
    public function text_is_never_altered_only_stripped_of_markers(): void
    {
        $blocks = ReportTextBlocks::build('1. نص الملاحظة كما هو');

        $this->assertSame('نص الملاحظة كما هو', $blocks[0]['text']);
    }
}