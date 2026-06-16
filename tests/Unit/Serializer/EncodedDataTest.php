<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Serializer\EncodedData;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\EncodedData
 */
class EncodedDataTest extends TestCase
{
    public function testGetters(): void
    {
        $object = new EncodedData('abc', EncodedData::FORMAT_FORM);

        $this->assertSame('abc', $object->getData());
        $this->assertSame(EncodedData::FORMAT_FORM, $object->getFormat());
    }
}
