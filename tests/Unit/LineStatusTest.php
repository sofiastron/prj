<?php

namespace App\Tests\Unit;

use App\Enum\LineStatus;
use PHPUnit\Framework\TestCase;

class LineStatusTest extends TestCase
{
    public function testHappyPath(): void
    {
        $this->assertTrue(LineStatus::PENDING->canTransitionTo(LineStatus::PREPARING));
        $this->assertTrue(LineStatus::PREPARING->canTransitionTo(LineStatus::READY));
        $this->assertTrue(LineStatus::READY->canTransitionTo(LineStatus::SERVED));
    }

    public function testNoSkippingNoGoingBack(): void
    {
        $this->assertFalse(LineStatus::PENDING->canTransitionTo(LineStatus::SERVED));
        $this->assertFalse(LineStatus::READY->canTransitionTo(LineStatus::PENDING));
        $this->assertFalse(LineStatus::SERVED->canTransitionTo(LineStatus::PREPARING));
    }

    public function testCancellationRules(): void
    {
        $this->assertTrue(LineStatus::PENDING->canTransitionTo(LineStatus::CANCELLED));
        $this->assertTrue(LineStatus::READY->canTransitionTo(LineStatus::CANCELLED));
        $this->assertFalse(LineStatus::SERVED->canTransitionTo(LineStatus::CANCELLED));
        $this->assertFalse(LineStatus::CANCELLED->canTransitionTo(LineStatus::CANCELLED));
    }
}
