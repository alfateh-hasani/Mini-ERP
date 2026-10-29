<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormatHelperTest extends TestCase
{
    public function testSarIsTheOnlyMoneyPresentation(): void { self::assertSame('SAR 1,234.50', sar('1234.5')); }
    public function testCsvCellsThatCouldBeFormulasAreNeutralized(): void { self::assertSame("'=SUM(A1:A2)", csv_safe('=SUM(A1:A2)')); self::assertSame("'+123", csv_safe('+123')); self::assertSame('Arabic', csv_safe('Arabic')); }
}
