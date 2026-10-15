<?php

namespace Omnilab\MaterialsProject\Tests;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\MaterialsProject\MaterialsProjectSource;
use Omnilab\MaterialsProject\MaterialsProjectSourceFactory;
use Omnilab\Model\Domain;
use Omnilab\Model\Quantity;
use Omnilab\Source\Capability;
use Omnilab\Source\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Not tested live (no key): the answers in Fixtures/ are built on the
 * API's OpenAPI schema, their numbers placeholders (Fixtures/README.md);
 * no-key.json is the API's real 401.
 */
final class MaterialsProjectSourceTest extends TestCase
{
    /** @var list<array{string, array<string, string>, array<string, mixed>}> */
    private array $calls = [];

    private function source(?MockResponse $response = null): MaterialsProjectSource
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $this->calls[] = [(string) parse_url($url, \PHP_URL_PATH), $query, $options['normalized_headers']['x-api-key'] ?? []];
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                'mp-149' === ($query['material_ids'] ?? null) => 'summary-mp-149.json',
                'Fe2O3' === ($query['formula'] ?? null) && '0' === $query['_skip'] => 'summary-Fe2O3-0.json',
                default => 'summary-empty.json',
            };

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture));
        });
        $source = (new MaterialsProjectSourceFactory($http))->create(['api_key' => 'test-key']);
        self::assertInstanceOf(MaterialsProjectSource::class, $source);

        return $source;
    }

    public function testAMaterialByItsIdWithTheKeyInItsHeader(): void
    {
        $silicon = $this->source()->entity('mp-149');

        [$path, $query, $key] = $this->calls[0];
        self::assertSame('/materials/summary/', $path);
        self::assertSame(['X-API-KEY: test-key'], $key);
        self::assertSame('legacy', $query['id_format'], 'ids written mp-149, not in their AlphaID form');
        self::assertStringContainsString('formation_energy_per_atom', $query['_fields']);
        self::assertSame('materialsproject:mp-149', $silicon->id->key());
        self::assertSame('Si (Fd-3m)', $silicon->label);
        self::assertSame(Domain::CHEMISTRY, $silicon->domain);
        self::assertSame('material', $silicon->kind);
        self::assertSame('https://materialsproject.org/materials/mp-149/', $silicon->url);
        self::assertSame('Si', $silicon->value('formula'));
        self::assertSame('Cubic', $silicon->value('crystal_system'));
        self::assertSame(227, $silicon->value('space_group_number'));
        self::assertSame('g/cm³', $silicon->property('density')->unit());
        self::assertSame('eV/atom', $silicon->property('formation_energy_per_atom')->unit());
        self::assertEquals(new Quantity(0.5, 'eV'), $silicon->value('band_gap'));
        self::assertEquals(new Quantity(90.0, 'GPa'), $silicon->value('bulk_modulus'));
        self::assertTrue($silicon->value('stable'));
        self::assertFalse($silicon->value('metal'));
        self::assertSame('icsd: icsd-00001', $silicon->value('xref'));
    }

    public function testSearchByFormulaOrChemicalSystem(): void
    {
        $source = $this->source();
        $page = $source->search(new Query('Fe2O3', limit: 2));
        self::assertSame(['materialsproject:mp-19770', 'materialsproject:mp-24972'], array_map(static fn ($e) => $e->id->key(), $page->items));
        self::assertSame(5, $page->total);
        self::assertSame('2', $page->next);
        self::assertSame(['Fe', 'O'], array_map('strval', $page->first()->properties('element')));

        $source->search(new Query('Fe-O'));
        self::assertSame('Fe-O', $this->calls[1][1]['chemsys']);
        self::assertArrayNotHasKey('formula', $this->calls[1][1]);
        self::assertCount(0, $source->search(new Query('Fe2O3', Domain::BIOLOGY)));
    }

    public function testUnknownNullOtherBasesRefusedAndNoKey(): void
    {
        self::assertNull($this->source()->entity('mp-999999999'));
        try {
            $this->source()->entity('cod:9008565');
            self::fail('cod');
        } catch (NotSupportedException) {
        }
        self::assertSame([Capability::ENTITY, Capability::SEARCH], $this->source()->capabilities());
        $calls = \count($this->calls);
        try {
            (new MaterialsProjectSourceFactory(new MockHttpClient()))->create()->entity('mp-149');
            self::fail('a key is required');
        } catch (ProviderException $e) {
            self::assertStringContainsString('an API key is required', $e->getMessage());
        }
        self::assertCount($calls, $this->calls, 'refused before any call');
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('No API key found in request');
        $this->source(new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/no-key.json'), ['http_code' => 401]))->entity('mp-149');
    }
}
