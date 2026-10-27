# omnilab/materials-project

The Materials Project's API (`https://api.materialsproject.org/`, its `materials/summary/`
endpoint) for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): the computed
properties of inorganic materials by their id (`mp-149`) - formula, symmetry, density, energies per
atom, stability, band gap, magnetism, elastic moduli -, and the materials of a formula or a chemical
system, as entities. An account's key is required.

**Not tested live**: no Materials Project key (see [Verified, and not](#verified-and-not)).

## Installation

```sh
composer require omnilab/materials-project
```

```php
use Omnilab\MaterialsProject\MaterialsProjectSourceFactory;

$mp = (new MaterialsProjectSourceFactory($httpClient))->create([
    'api_key' => getenv('MP_API_KEY'),   // an account's key, which every call needs (never written in code)
    'mailto' => 'support@glitchr.io',    // optional
]);
```

Without `api_key`, the source is built (so that a registry of every source loads), and each call is refused before it is made with a `ProviderException` ("an API key is required").

## Credentials

Every call needs the API key of a Materials Project account (free, on registering at
materialsproject.org; the key is on the account's dashboard), given as `api_key` and sent in the
`X-API-KEY` header. Without it the source is built - so that a registry of every source loads -
and each call is refused before it is made. Heavy use is announced to
heavy.api.use@materialsproject.org, as the API's documentation asks.

## Options

The factory's options, with the defaults it applies (`MaterialsProjectSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `api_key` | `?string` | null | no | the account's key, sent as `X-API-KEY`; null or empty: every call refused with a `ProviderException` before it is made |
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls: the Materials Project publishes no rate |
| `base_uri` | `string` | `https://api.materialsproject.org/` | no | the API the source calls |

## Capabilities

`capabilities()` answers `entity`, `search`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | an mp id (`mp-149`, `mvc-...`, its page, `materialsproject:`); any other identifier refused before any call; with the key only |
| `search` | yes | a formula (`Fe2O3`, `AB2`, `Fe*O3`) or, with a dash, a chemical system (`Fe-O`); 1,000 a page at most; `total` from `meta.total_doc`; a domain other than chemistry, or an empty text, answers an empty page without a call |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | no |  |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | no | none: other databases' ids are a property (`xref`) |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no |  |
| `station` | no |  |
| `cloud_cover` | no |  |

## The scheme

A material is named in the core's `materialsproject` scheme: `mp-149`, lower case (another prefix,
`mvc-...`, read too), read by its shape, from `materialsproject:` and from its page
`materialsproject.org/materials/mp-149/`. Wikidata keeps these ids in P9902 (on one item on
2026-10-15). The API also writes ids in an "AlphaID" form (`mp-aaaaaaft`); the source asks
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

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Exception\ProviderException;
use Omnilab\MaterialsProject\MaterialsProjectSourceFactory;
use Omnilab\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$mp = (new MaterialsProjectSourceFactory(HttpClient::create()))->create([
    'api_key' => getenv('MP_API_KEY') ?: null,   // an account's key: every call needs one
    'mailto' => 'you@example.org',
]);

try {
    $silicon = $mp->entity('mp-149');
    echo $silicon?->label, ': band gap ', $silicon?->property('band_gap'), ', ', $silicon?->property('energy_above_hull'), " above the hull\n";
    foreach ($mp->search(new Query('Fe-O', limit: 5)) as $material) {
        echo '  ', $material->id, ' ', $material->label, ' ', $material->property('formation_energy_per_atom'), "\n";
    }
} catch (ProviderException $e) {
    echo $e->getMessage(), "\n";
}
```

Without a key (run on 2026-10-27, no network needed: the call is refused before it is made):

```
[materials-project] an API key is required (the api_key option): the Materials Project answers nothing without one
```

With a key the script prints silicon and five materials of the Fe-O system: not run here (no
account).

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        materials-project: { factory: materials-project, options: { api_key: '%env(MP_API_KEY)%', mailto: '%env(OMNILAB_MAILTO)%' } }
```

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call: an identifier that is not of the `materialsproject` scheme
(`NotSupportedException`), and any call without a key (`ProviderException`, `status` null). The
API's 401 (a wrong key) and 422 (a query it cannot read) are `ProviderException`s with its
message; an unknown id is an empty `data`, so null.

## Verified, and not

**Not tested live.** On 2026-10-15: a call without a key answers 401 `{"message":"No API key found
in request"}` (the fixture `no-key.json` is that answer); the OpenAPI document
(`api.materialsproject.org/openapi.json`, version 0.87.3) is public and was read for the
endpoint, its parameters, the header and the fields. No account was created, so nothing else was
called: the fixtures `summary-*.json` are built on that schema - real ids, formulas and space
groups, **placeholder numbers** (`Tests/Fixtures/README.md`). Not verified: every answer of the
API itself, its rate limits, the AlphaID form of the ids in answers without `id_format`.

On 2026-10-27 the example below ran without a key: the refusal before any call, as tested.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the Materials Project (Lawrence Berkeley National Laboratory). The Materials Project
asks to be cited (A. Jain et al., APL Materials 1, 011002, 2013), states its terms on its website
and distinguishes data under commercial and non-commercial licences, as the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-materials-project#terms-of-use) says.
Whoever runs the package holds the account and its key, keeps to those terms, and is responsible
for any use made of what it returns: see the README's
[Responsibility](https://github.com/glitchr-omnisense/omnilab-materials-project#responsibility)
section.
