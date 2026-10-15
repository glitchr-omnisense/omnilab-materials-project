# omnilab/materials-project

**Not tested live**: no Materials Project key (see [Verified, and not](#verified-and-not)).

## Installation

```sh
composer require omnilab/materials-project
```

```php
use Omnilab\MaterialsProject\MaterialsProjectSourceFactory;

$mp = (new MaterialsProjectSourceFactory($httpClient))->create([
    'api_key' => getenv('MP_API_KEY'),   // required: an account's key (never written in code)
    'mailto' => 'support@glitchr.io',    // optional
]);
```

Without `api_key`, the factory throws `InvalidConfigException`.

## The scheme

A material is named in the core's `materialsproject` scheme: `mp-149`, lower case (another prefix,
`mvc-...`, read too), read by its shape, from `materialsproject:` and from its page
`materialsproject.org/materials/mp-149/`. Wikidata keeps these ids in P9902 (on one item on
2026-10-16). The API also writes ids in an "AlphaID" form (`mp-aaaaaaft`); the source asks
`id_format=legacy` so that every answer gives `mp-149`, and the scheme reads that form only.

## Which API

`api.materialsproject.org`, its summary endpoint, the key in the `X-API-KEY` header:

| Method | Call |
|---|---|
| `entity()` | `materials/summary/?material_ids={id}&_fields=...&id_format=legacy&_limit=1` |
| `search()` | `materials/summary/?formula={formula}` (or `chemsys={a-b}` when the text holds a dash) `&_fields=...&id_format=legacy&_skip=...&_limit=...`; `total` from `meta.total_doc` |

An unknown id is an empty `data` (null); 401 (no key, a wrong key) and 422 (a bad query) are
`ProviderException`s with the API's message.

## What an entity holds

`id`, `label` (its formula and space group: `Si (Fd-3m)`), `domain` chemistry, `kind`
`material`, `url` (its page), and:

| Key | Field | Value |
|---|---|---|
| `formula`, `chemical_system`, `element` (one each) | formula_pretty, chemsys, elements | text |
| `sites` | nsites | number |
| `density`, `density_atomic`, `volume` | density, density_atomic, volume | g/cm³, Å³/atom, Å³ |
| `crystal_system`, `space_group`, `space_group_number`, `point_group` | symmetry | text, number |
| `energy_per_atom`, `formation_energy_per_atom`, `energy_above_hull` | the same | eV/atom |
| `stable`, `direct_gap`, `metal`, `theoretical`, `deprecated` | is_stable, is_gap_direct, is_metal, theoretical, deprecated | booleans |
| `band_gap`, `fermi_energy` | band_gap, efermi | eV |
| `magnetic_ordering`, `total_magnetization` | ordering, total_magnetization | text, μB |
| `bulk_modulus`, `shear_modulus` | their Voigt-Reuss-Hill average | GPa |
| `xref` | database_IDs | `icsd: ...`: bases the core has no scheme for |
| `last_updated` | last_updated | text |

The units are those the schema's descriptions give. No identifier of another base: the `Collector`
has nothing to follow.

## Verified, and not

**Not tested live.** On 2026-10-16: a call without a key answers 401 `{"message":"No API key found
in request"}` (the fixture `no-key.json` is that answer); the OpenAPI document
(`api.materialsproject.org/openapi.json`, version 0.87.3) is public and was read for the
endpoint, its parameters, the header and the fields. No account was created, so nothing else was
called: the fixtures `summary-*.json` are built on that schema - real ids, formulas and space
groups, **placeholder numbers** (`Tests/Fixtures/README.md`). Not verified: every answer of the
API itself, its rate limits, the AlphaID form of the ids in answers without `id_format`.
