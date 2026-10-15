<?php

namespace Omnilab\MaterialsProject;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Materials Project (Lawrence Berkeley National Laboratory), computed
 * properties of inorganic materials, through its API
 * (api.materialsproject.org, an account's key required): a material by
 * its id ("mp-149") - its formula, chemical system, elements, sites,
 * density, volume, symmetry (crystal system, space group, point group),
 * energies per atom (total, formation, above the hull), stability, band
 * gap, metal or not, magnetism, elastic moduli, whether theoretical, the
 * ids it has in other databases (ICSD...) -; the materials of a formula
 * or a chemical system.
 *
 * One call an entity or a page (its summary endpoint, the fields asked).
 * Not tested live: no key was available (README).
 */
final class MaterialsProjectSource extends HttpSource
{
    public const BASE_URI = 'https://api.materialsproject.org/';

    /** The most materials a page holds (the API's limit is 1000). */
    public const MAX_LIMIT = 1000;

    /** The summary fields asked: what an entity holds. */
    public const FIELDS = ['material_id', 'formula_pretty', 'chemsys', 'elements', 'nelements', 'nsites', 'density', 'density_atomic', 'volume', 'symmetry', 'energy_per_atom', 'formation_energy_per_atom', 'energy_above_hull', 'is_stable', 'band_gap', 'is_gap_direct', 'is_metal', 'efermi', 'ordering', 'total_magnetization', 'bulk_modulus', 'shear_modulus', 'theoretical', 'database_IDs', 'deprecated', 'last_updated'];

    public function __construct(
        HttpClientInterface $http,
        string $apiKey,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, ['X-API-KEY' => $apiKey] + $headers, $throttle);
    }

    public function getName(): string
    {
        return 'materials-project';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH];
    }

    /**
     * A material by its id ("mp-149", "materialsproject:mp-149", its page);
     * another base's identifier is refused before any call; null when the
     * Materials Project has none.
     */
    public function entity(Identifier|string $id): ?Entity
    {
        $identifier = self::identify($id, Scheme::MATERIALS_PROJECT) ?? throw NotSupportedException::identifier('materials-project', (string) $id);
        $data = $this->getJson('materials/summary/', ['material_ids' => $identifier->value, '_fields' => implode(',', self::FIELDS), 'id_format' => 'legacy', '_limit' => 1])['data'] ?? [];

        return \is_array($data[0] ?? null) ? $this->toEntity($data[0]) : null;
    }

    /**
     * The materials of a formula ("Fe2O3", "SiO2", an anonymous "AB2", a
     * wildcard "Fe*O3") or of a chemical system ("Fe-O": a dash), in the
     * API's order; a domain other than chemistry answers nothing. An
     * entity of a page holds what entity() gives.
     */
    public function search(Query $query): Page
    {
        if (null !== $query->domain && Domain::CHEMISTRY !== $query->domain) {
            return new Page();
        }
        $text = (string) preg_replace('~\s+~', '', $query->text);
        if ('' === $text) {
            return new Page();
        }
        $skip = max(0, (int) $query->cursor);
        $limit = max(1, min(self::MAX_LIMIT, $query->limit));
        $answer = $this->getJson('materials/summary/', [str_contains($text, '-') ? 'chemsys' : 'formula' => $text, '_fields' => implode(',', self::FIELDS), 'id_format' => 'legacy', '_skip' => $skip, '_limit' => $limit]) ?? [];
        $items = array_values(array_filter(array_map(fn (mixed $doc) => \is_array($doc) ? $this->toEntity($doc) : null, $answer['data'] ?? [])));
        $total = isset($answer['meta']['total_doc']) ? (int) $answer['meta']['total_doc'] : null;
        $more = null !== $total ? $skip + $limit < $total : \count($items) === $limit;

        return new Page($items, $more ? (string) ($skip + $limit) : null, $total);
    }

    /** @param array<string, mixed> $doc a summary document */
    private function toEntity(array $doc): ?Entity
    {
        $id = Identifier::tryOf(Scheme::MATERIALS_PROJECT, \is_string($doc['material_id'] ?? null) ? $doc['material_id'] : null);
        if (null === $id) {
            return null;
        }
        $properties = [];
        $add = static function (string $key, string $label, mixed $value) use (&$properties): void {
            if (null !== $value && '' !== $value) {
                $properties[] = new Property($key, $label, $value, 'materials-project');
            }
        };
        $quantity = static fn (mixed $value, string $unit): ?Quantity => is_numeric($value) ? new Quantity(0 + $value, $unit) : null;
        $add('formula', 'Formula', \is_string($doc['formula_pretty'] ?? null) ? $doc['formula_pretty'] : null);
        $add('chemical_system', 'Chemical system', \is_string($doc['chemsys'] ?? null) ? $doc['chemsys'] : null);
        foreach ((array) ($doc['elements'] ?? []) as $element) {
            $add('element', 'Element', \is_string($element) ? $element : null);
        }
        $add('sites', 'Sites in the cell', \is_int($doc['nsites'] ?? null) ? $doc['nsites'] : null);
        $add('density', 'Density', $quantity($doc['density'] ?? null, 'g/cm³'));
        $add('density_atomic', 'Volume per atom', $quantity($doc['density_atomic'] ?? null, 'Å³/atom'));
        $add('volume', 'Cell volume', $quantity($doc['volume'] ?? null, 'Å³'));
        $symmetry = \is_array($doc['symmetry'] ?? null) ? $doc['symmetry'] : [];
        $add('crystal_system', 'Crystal system', \is_string($symmetry['crystal_system'] ?? null) ? $symmetry['crystal_system'] : null);
        $add('space_group', 'Space group', \is_string($symmetry['symbol'] ?? null) ? $symmetry['symbol'] : null);
        $add('space_group_number', 'Space group number', \is_int($symmetry['number'] ?? null) ? $symmetry['number'] : null);
        $add('point_group', 'Point group', \is_string($symmetry['point_group'] ?? null) ? $symmetry['point_group'] : null);
        $add('energy_per_atom', 'Energy per atom (corrected)', $quantity($doc['energy_per_atom'] ?? null, 'eV/atom'));
        $add('formation_energy_per_atom', 'Formation energy per atom', $quantity($doc['formation_energy_per_atom'] ?? null, 'eV/atom'));
        $add('energy_above_hull', 'Energy above the hull', $quantity($doc['energy_above_hull'] ?? null, 'eV/atom'));
        $add('stable', 'Stable (on the hull)', \is_bool($doc['is_stable'] ?? null) ? $doc['is_stable'] : null);
        $add('band_gap', 'Band gap', $quantity($doc['band_gap'] ?? null, 'eV'));
        $add('direct_gap', 'Direct band gap', \is_bool($doc['is_gap_direct'] ?? null) ? $doc['is_gap_direct'] : null);
        $add('metal', 'A metal', \is_bool($doc['is_metal'] ?? null) ? $doc['is_metal'] : null);
        $add('fermi_energy', 'Fermi energy', $quantity($doc['efermi'] ?? null, 'eV'));
        $add('magnetic_ordering', 'Magnetic ordering', \is_string($doc['ordering'] ?? null) ? $doc['ordering'] : null);
        $add('total_magnetization', 'Total magnetization', $quantity($doc['total_magnetization'] ?? null, 'μB'));
        foreach (['bulk_modulus' => 'Bulk modulus (Voigt-Reuss-Hill)', 'shear_modulus' => 'Shear modulus (Voigt-Reuss-Hill)'] as $key => $label) {
            $add($key, $label, $quantity($doc[$key]['vrh'] ?? null, 'GPa'));
        }
        $add('theoretical', 'Theoretical (no experimental match)', \is_bool($doc['theoretical'] ?? null) ? $doc['theoretical'] : null);
        $add('deprecated', 'Deprecated', \is_bool($doc['deprecated'] ?? null) ? $doc['deprecated'] : null);
        $add('last_updated', 'Last updated', \is_string($doc['last_updated'] ?? null) ? $doc['last_updated'] : null);
        // Its ids in other databases ("icsd": ["icsd-..."]): bases the core has no scheme for.
        foreach ((array) ($doc['database_IDs'] ?? []) as $base => $ids) {
            foreach ((array) $ids as $other) {
                $add('xref', 'Other database', \is_string($other) ? $base.': '.$other : null);
            }
        }

        return new Entity(
            id: $id,
            label: \is_string($doc['formula_pretty'] ?? null) ? $doc['formula_pretty'].(\is_string($symmetry['symbol'] ?? null) ? ' ('.$symmetry['symbol'].')' : '') : $id->value,
            domain: Domain::CHEMISTRY,
            kind: 'material',
            properties: $properties,
            url: $id->url(),
            source: 'materials-project',
        );
    }
}
