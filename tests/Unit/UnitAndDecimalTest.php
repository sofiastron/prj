<?php

namespace App\Tests\Unit;

use App\Enum\Dimension;
use App\Enum\Unit;
use App\Service\Decimal;
use PHPUnit\Framework\TestCase;

class UnitAndDecimalTest extends TestCase
{
    public function testConversionToBaseUnit(): void
    {
        $this->assertSame('1500.000000', Unit::KG->toBase('1.5'));
        $this->assertSame('250.000000', Unit::CL->toBase('25'));
        $this->assertSame('2000.000000', Unit::L->toBase('2'));
        $this->assertSame('3.000000', Unit::PIECE->toBase('3'));
        $this->assertSame('1.500000', Unit::KG->fromBase('1500'));
    }

    public function testDimensionsAreConsistent(): void
    {
        $this->assertSame(Dimension::MASS, Unit::KG->dimension());
        $this->assertSame(Dimension::VOLUME, Unit::CL->dimension());
        $this->assertSame(Dimension::COUNT, Unit::PIECE->dimension());
        $this->assertSame(Unit::G, Dimension::MASS->baseUnit());
        $this->assertCount(2, Dimension::MASS->units());
        $this->assertCount(3, Dimension::VOLUME->units());
    }

    public function testPriceConversionPerBaseUnit(): void
    {
        // 80 MAD/kg = 0,08 MAD/g
        $this->assertSame('0.080000', Unit::KG->priceToBase('80'));
    }

    public function testHumanReadableFormat(): void
    {
        $this->assertSame('1.5 kg', Unit::format(Dimension::MASS, '1500'));
        $this->assertSame('250 g', Unit::format(Dimension::MASS, '250.000'));
        $this->assertSame('2 l', Unit::format(Dimension::VOLUME, '2000'));
        $this->assertSame('12 pce', Unit::format(Dimension::COUNT, '12'));
    }

    public function testDecimalIsExactWhereFloatIsNot(): void
    {
        // 0.1 + 0.2 != 0.3 en float ; exact en bcmath.
        $this->assertSame('0.3', Decimal::add('0.1', '0.2', 1));
        $this->assertSame(0, Decimal::cmp(Decimal::add('0.1', '0.2'), '0.3'));
    }

    public function testRoundingIsHalfUpAndSymmetric(): void
    {
        $this->assertSame('1.24', Decimal::round('1.235', 2));
        $this->assertSame('1.23', Decimal::round('1.2349', 2));
        $this->assertSame('-1.24', Decimal::round('-1.235', 2));
    }

    public function testFloorIntForPortions(): void
    {
        $this->assertSame(3, Decimal::floorInt(Decimal::div('1000', '300')));
        $this->assertSame(0, Decimal::floorInt('0.99'));
    }

    public function testDivisionByZeroIsRejected(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        Decimal::div('1', '0');
    }
}
