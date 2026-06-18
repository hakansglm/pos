<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Serializer\EncodedData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncodedData::class)]
class EncodedDataTest extends TestCase
{
    public function testGetters(): void
    {
        $object = new EncodedData('abc', EncodedData::FORMAT_FORM);

        $this->assertSame('abc', $object->getData());
        $this->assertSame(EncodedData::FORMAT_FORM, $object->getFormat());
    }
}
