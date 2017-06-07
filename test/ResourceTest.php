<?php

namespace HalTest;

use Hal\Link;
use Hal\Resource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResourceTest extends TestCase
{
    public function testCanConstructWithData()
    {
        $resource = new Resource(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $resource->getElements());
    }

    public function invalidElementNames()
    {
        return [
            'empty'     => ['', 'cannot be empty'],
            '_links'    => ['_links', 'reserved element $name'],
            '_embedded' => ['_embedded', 'reserved element $name'],
        ];
    }

    /**
     * @dataProvider invalidElementNames
     */
    public function testInvalidDataNamesRaiseExceptionsDuringConstruction(string $name, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $resource = new Resource([$name => 'bar']);
    }

    public function testCanConstructWithDataContainingEmbeddedResources()
    {
        $embedded = new Resource(['foo' => 'bar']);
        $resource = new Resource(['foo' => $embedded]);
        $this->assertEquals(['foo' => $embedded], $resource->getElements());
        $representation = $resource->toArray();
        $this->assertArrayHasKey('_embedded', $representation);
        $this->assertArrayHasKey('foo', $representation['_embedded']);
        $this->assertEquals(['foo' => 'bar'], $representation['_embedded']['foo']);
    }

    public function testCanConstructWithLinks()
    {
        $links = [
            new Link('self', 'https://example.com/'),
            new Link('about', 'https://example.com/about'),
        ];
        $resource = new Resource([], $links);
        $this->assertSame($links, $resource->getLinks());
    }

    public function testNonLinkItemsRaiseExceptionDuringConstruction()
    {
        $links = [
            new Link('self', 'https://example.com/'),
            'foo',
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$links');
        $resource = new Resource([], $links);
    }

    public function testCanConstructWithEmbeddedResources()
    {
        $embedded = new Resource(['foo' => 'bar']);
        $resource = new Resource([], [], ['foo' => $embedded]);
        $this->assertEquals(['foo' => $embedded], $resource->getElements());
        $representation = $resource->toArray();
        $this->assertArrayHasKey('_embedded', $representation);
        $this->assertArrayHasKey('foo', $representation['_embedded']);
        $this->assertEquals(['foo' => 'bar'], $representation['_embedded']['foo']);
    }

    public function testNonResourceOrCollectionItemsRaiseExceptionDuringConstruction()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid embedded resource');
        $resource = new Resource([], [], ['foo' => 'bar']);
    }

    /**
     * @dataProvider invalidElementNames
     */
    public function testInvalidResourceNamesRaiseExceptionsDuringConstruction(string $name, string $expectedMessage)
    {
        $embedded = new Resource(['foo' => 'bar']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $resource = new Resource([], [], [$name => $embedded]);
    }

    public function testWithLinkReturnsNewInstanceContainingNewLink()
    {
        $link = new Link('self');
        $resource = new Resource();
        $new = $resource->withLink($link);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getLinksByRel('self'));
        $this->assertEquals([$link], $new->getLinksByRel('self'));
    }

    public function testWithLinkReturnsSameInstanceIfAlreadyContainsLinkInstance()
    {
        $link = new Link('self');
        $resource = new Resource([], [$link]);
        $new = $resource->withLink($link);
        $this->assertSame($resource, $new);
    }

    public function testWithoutLinkReturnsNewInstanceRemovingLink()
    {
        $link = new Link('self');
        $resource = new Resource([], [$link]);
        $new = $resource->withoutLink($link);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([$link], $resource->getLinksByRel('self'));
        $this->assertEquals([], $new->getLinksByRel('self'));
    }

    public function testWithoutLinkReturnsSameInstanceIfLinkIsNotPresent()
    {
        $link = new Link('self');
        $resource = new Resource();
        $new = $resource->withoutLink($link);
        $this->assertSame($resource, $new);
    }

    public function testGetLinksByRelReturnsAllLinksWithGivenRelationshipAsArray()
    {
        $link1 = new Link('self');
        $link2 = new Link('about');
        $link3 = new Link('self');
        $resource = new Resource();

        $resource = $resource
            ->withLink($link1)
            ->withLink($link2)
            ->withLink($link3);

        $links = $resource->getLinksByRel('self');
        // array_values needed here, as keys will no longer be sequential
        $this->assertEquals([$link1, $link3], array_values($links));
    }

    /**
     * @dataProvider invalidElementNames
     */
    public function testWithElementRaisesExceptionForInvalidName(string $name, string $expectedMessage)
    {
        $resource = new Resource();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $resource->withElement($name, 'foo');
    }

    public function testWithElementRaisesExceptionIfNameCollidesWithExistingResource()
    {
        $embedded = new Resource(['foo' => 'bar']);
        $resource = new Resource(['foo' => $embedded]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('element matching resource');
        $resource->withElement('foo', 'bar');
    }

    public function testWithElementReturnsNewInstanceWithNewElement()
    {
        $resource = new Resource();
        $new = $resource->withElement('foo', 'bar');
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getElements());
        $this->assertEquals(['foo' => 'bar'], $new->getElements());
    }

    public function testWithElementReturnsNewInstanceOverwritingExistingElementValue()
    {
        $resource = new Resource(['foo' => 'bar']);
        $new = $resource->withElement('foo', 'baz');
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => 'bar'], $resource->getElements());
        $this->assertEquals(['foo' => 'baz'], $new->getElements());
    }

    public function testWithElementProxiesToEmbedIfResourceValueProvided()
    {
        $embedded = new Resource(['foo' => 'bar']);
        $resource = new Resource();
        $new = $resource->withElement('foo', $embedded);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getElements());
        $this->assertEquals(['foo' => $embedded], $new->getElements());
        $representation = $new->toArray();
        $this->assertArrayHasKey('_embedded', $representation);
        $this->assertArrayHasKey('foo', $representation['_embedded']);
        $this->assertEquals(['foo' => 'bar'], $representation['_embedded']['foo']);
    }

    public function testWithElementProxiesToEmbedIfResourceCollectionValueProvided()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['foo' => 'baz']);
        $resource3 = new Resource(['foo' => 'bat']);
        $collection = [$resource1, $resource2, $resource3];

        $resource = new Resource();
        $new = $resource->withElement('foo', $collection);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getElements());
        $this->assertEquals(['foo' => $collection], $new->getElements());
    }

    /**
     * @dataProvider invalidElementNames
     */
    public function testEmbedRaisesExceptionForInvalidName(string $name, string $expectedMessage)
    {
        $resource = new Resource();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $resource->embed($name, new Resource());
    }

    public function testEmbedRaisesExceptionIfNameCollidesWithExistingData()
    {
        $resource = new Resource(['foo' => 'bar']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('embed resource matching element');
        $resource->embed('foo', new Resource());
    }

    public function testEmbedReturnsNewInstanceWithEmbeddedResource()
    {
        $embedded = new Resource(['foo' => 'bar']);
        $resource = new Resource();
        $new = $resource->embed('foo', $embedded);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getElements());
        $this->assertEquals(['foo' => $embedded], $new->getElements());
    }

    public function testEmbedReturnsNewInstanceWithEmbeddedCollection()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['foo' => 'baz']);
        $resource3 = new Resource(['foo' => 'bat']);
        $collection = [$resource1, $resource2, $resource3];

        $resource = new Resource();
        $new = $resource->embed('foo', $collection);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getElements());
        $this->assertEquals(['foo' => $collection], $new->getElements());
    }

    public function testEmbedReturnsNewInstanceAppendingResourceToExistingResource()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['foo' => 'baz']);

        $resource = new Resource(['foo' => $resource1]);
        $new = $resource->embed('foo', $resource2);
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => $resource1], $resource->getElements());
        $this->assertEquals(['foo' => [$resource1, $resource2]], $new->getElements());
    }

    public function testEmbedReturnsNewInstanceAppendingResourceToExistingCollection()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['foo' => 'baz']);
        $resource3 = new Resource(['foo' => 'bat']);
        $collection = [$resource1, $resource2];

        $resource = new Resource(['foo' => $collection]);
        $new = $resource->embed('foo', $resource3);
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => $collection], $resource->getElements());
        $this->assertEquals(['foo' => [$resource1, $resource2, $resource3]], $new->getElements());
    }

    public function testEmbedReturnsNewInstanceAppendingCollectionToExistingCollection()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['foo' => 'baz']);
        $resource3 = new Resource(['foo' => 'bat']);
        $resource4 = new Resource(['foo' => 'bat']);
        $collection1 = [$resource1, $resource2];
        $collection2 = [$resource3, $resource4];

        $resource = new Resource(['foo' => $collection1]);
        $new = $resource->embed('foo', $collection2);
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => $collection1], $resource->getElements());
        $this->assertEquals(['foo' => $collection1 + $collection2], $new->getElements());
    }

    public function testEmbedRaisesExceptionIfNewResourceDoesNotMatchStructureOfExisting()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['bar' => 'baz']);

        $resource = new Resource(['foo' => $resource1]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('structurally inequivalent');
        $resource->embed('foo', $resource2);
    }

    public function testEmbedRaisesExceptionIfNewResourceDoesNotMatchCollectionResourceStructure()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['foo' => 'baz']);
        $resource3 = new Resource(['bar' => 'bat']);
        $collection = [$resource1, $resource2];

        $resource = new Resource(['foo' => $collection]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('structurally inequivalent');
        $resource->embed('foo', $resource3);
    }

    public function testEmbedRaisesExceptionIfResourcesInCollectionAreNotOfSameStructure()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['bar' => 'bat']);
        $collection = [$resource1, $resource2];

        $resource = new Resource();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('structurally inequivalent');
        $resource->embed('foo', $collection);
    }

    public function testWithElementsAddsNewDataToNewResourceInstance()
    {
        $resource = new Resource();
        $new = $resource->withElements(['foo' => 'bar']);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getElements());
        $this->assertEquals(['foo' => 'bar'], $new->getElements());
    }

    public function testWithElementsAddsNewEmbeddedResourcesToNewResourceInstance()
    {
        $embedded = new Resource(['foo' => 'bar']);
        $resource = new Resource();
        $new = $resource->withElements(['foo' => $embedded]);
        $this->assertNotSame($resource, $new);
        $this->assertEquals([], $resource->getElements());
        $this->assertEquals(['foo' => $embedded], $new->getElements());
        $representation = $new->toArray();
        $this->assertArrayHasKey('_embedded', $representation);
        $this->assertArrayHasKey('foo', $representation['_embedded']);
        $this->assertEquals(['foo' => 'bar'], $representation['_embedded']['foo']);
    }

    public function testWithElementsOverwritesExistingDataInNewResourceInstance()
    {
        $resource = new Resource(['foo' => 'bar']);
        $new = $resource->withElements(['foo' => 'baz']);
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => 'bar'], $resource->getElements());
        $this->assertEquals(['foo' => 'baz'], $new->getElements());
    }

    public function testWithElementsAppendsEmbeddedResourcesToExistingResourcesInNewResourceInstance()
    {
        $resource1 = new Resource(['foo' => 'bar']);
        $resource2 = new Resource(['foo' => 'bar']);
        $resource = new Resource(['foo' => $resource1]);
        $new = $resource->withElements(['foo' => $resource2]);

        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => $resource1], $resource->getElements());
        $this->assertEquals(['foo' => [$resource1, $resource2]], $new->getElements());
    }

    public function testWithoutElementRemovesDataElementIfItIsPresent()
    {
        $resource = new Resource(['foo' => 'bar']);
        $new = $resource->withoutElement('foo');
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => 'bar'], $resource->getElements());
        $this->assertEquals([], $new->getElements());
    }

    public function testWithoutElementDoesNothingIfElementOrResourceNotPresent()
    {
        $resource = new Resource(['foo' => 'bar']);
        $new = $resource->withoutElement('bar');
        $this->assertSame($resource, $new);
    }

    public function testWithoutElementRemovesEmbeddedResourceIfItIsPresent()
    {
        $embedded = new Resource();
        $resource = new Resource(['foo' => $embedded]);
        $new = $resource->withoutElement('foo');
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => $embedded], $resource->getElements());
        $this->assertEquals([], $new->getElements());
    }

    public function testWithoutElementRemovesEmbeddedCollectionIfPresent()
    {
        $resource1 = new Resource();
        $resource2 = new Resource();
        $resource3 = new Resource();
        $collection = [$resource1, $resource2, $resource3];
        $resource = new Resource(['foo' => $collection]);
        $new = $resource->withoutElement('foo');
        $this->assertNotSame($resource, $new);
        $this->assertEquals(['foo' => $collection], $resource->getElements());
        $this->assertEquals([], $new->getElements());
    }

    /**
     * @dataProvider invalidElementNames
     */
    public function testWithoutElementRaisesExceptionForInvalidElementName(string $name, string $expectedMessage)
    {
        $resource = new Resource();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $resource->withoutElement($name);
    }

    public function populatedResources()
    {
        $resource = (new Resource())
            ->withLink(new Link('self', '/api/foo'))
            ->withLink(new Link('about', '/doc/about'))
            ->withLink(new Link('about', '/doc/resources/foo'))
            ->withElements(['foo' => 'bar', 'id' => 12345678])
            ->embed('bar', new Resource(['bar' => 'baz'], [new Link('self', '/api/bar')]))
            ->embed('baz', [
                new Resource(['baz' => 'bat', 'id' => 987654], [new Link('self', '/api/baz/987654')]),
                new Resource(['baz' => 'bat', 'id' => 987653], [new Link('self', '/api/baz/987653')]),
            ]);
        $expected = [
            'foo' => 'bar',
            'id'  => 12345678,
            '_links' => [
                'self' => [
                    'href' => '/api/foo',
                ],
                'about' => [
                    ['href' => '/doc/about'],
                    ['href' => '/doc/resources/foo'],
                ],
            ],
            '_embedded' => [
                'bar' => [
                    'bar' => 'baz',
                    '_links' => [
                        'self' => ['href' => '/api/bar'],
                    ],
                ],
                'baz' => [
                    [
                        'baz' => 'bat',
                        'id'  => 987654,
                        '_links' => [
                            'self' => ['href' => '/api/baz/987654'],
                        ],
                    ],
                    [
                        'baz' => 'bat',
                        'id'  => 987653,
                        '_links' => [
                            'self' => ['href' => '/api/baz/987653'],
                        ],
                    ],
                ],
            ],
        ];

        yield 'fully-populated' => [$resource, $expected];
    }

    /**
     * @dataProvider populatedResources
     */
    public function testToArrayReturnsHalDataStructure(Resource $resource, array $expected)
    {
        $this->assertEquals($expected, $resource->toArray());
    }

    /**
     * @dataProvider populatedResources
     */
    public function testJsonSerializeReturnsHalDataStructure(Resource $resource, array $expected)
    {
        $this->assertEquals($expected, $resource->jsonSerialize());
    }
}
