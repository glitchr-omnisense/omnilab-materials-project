# omnilab/materials-project

> **Not tested live.** The Materials Project's API answers nothing without an account's key ("No API
> key found in request", 401, checked on 2026-10-15), and no account was created for this package.
> Its tests read answers built on the API's published OpenAPI schema (version 0.87.3), their numbers
> placeholders. Details in [docs/index.md](docs/index.md#verified-and-not).

The Materials Project (Lawrence Berkeley National Laboratory) for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): computed properties of inorganic
materials - a material by its id (`mp-149`): formula, chemical system, elements, sites, density,
volume, symmetry, energies per atom (total, formation, above the hull), stability, band gap, metal
or not, magnetism, elastic moduli, whether theoretical, its ids in other databases -; the
materials of a formula (`Fe2O3`) or a chemical system (`Fe-O`). An account's key is required (free,
on registering with the Materials Project).

```php
$mp = (new MaterialsProjectSourceFactory($http))->create(['api_key' => $key, 'mailto' => 'support@glitchr.io']);
$material = $mp->entity('mp-149');              // or 'materialsproject:mp-149', its page
$material->value('formula');                    // Si
$material->value('space_group');                // Fd-3m
$material->property('band_gap');                // ... eV
$mp->search(new Query('Fe2O3', limit: 20));     // the materials of that formula; 'Fe-O': of that system
```

```yaml
omnilab:
    sources:
        materials-project:
            factory: materials-project
            options:
                api_key: '%env(MP_API_KEY)%'   # every call needs it
                mailto: 'support@glitchr.io'   # in the User-Agent
```

## Install and a first call

```sh
composer require omnilab/materials-project
```

The Materials Project's API answers nothing without an account's key (free, at
materialsproject.org). This first call needs none: silicon (mp-149), its answer built after the
API's published schema (the formula and space group are silicon's), so nothing leaves the process
(Symfony's `MockHttpClient` answers in place of the service). A real call gives the factory no
client (it makes its own) and the source `['api_key' => getenv('MP_API_KEY')]`.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\MaterialsProject\MaterialsProjectSourceFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// The service's answer, recorded: no account, and nothing leaves the process.
$http = new MockHttpClient(new MockResponse('{"data": [{"material_id": "mp-149", "formula_pretty": "Si", "chemsys": "Si", "elements": ["Si"], "nelements": 1,
    "symmetry": {"crystal_system": "Cubic", "symbol": "Fd-3m", "number": 227, "point_group": "m-3m"}}], "meta": {"total_doc": 1}}'));
$mp = (new MaterialsProjectSourceFactory($http))->create(['api_key' => 'not-a-key']);
$silicon = $mp->entity('mp-149');
echo $silicon->id, ' ', $silicon->value('formula'), ', ', $silicon->value('space_group'), "\n";
```

Its output:

```
materialsproject:mp-149 Si, Fd-3m
```

## Terms of use

The Materials Project asks to be cited (A. Jain et al., The Materials Project: A materials genome
approach to accelerating materials innovation, APL Materials 1, 011002 (2013)) and states its
terms on its website; its API distinguishes data under a "commercial" and a "non-commercial"
licence (the `license` parameter). Read them before use. Heavy use is announced to
heavy.api.use@materialsproject.org, as the API's documentation asks.

## Usage policy

Declared as data in [`policy.json`](policy.json) (read at https://docs.materialsproject.org/downloading-data/using-the-api/tips-for-large-downloads on 2026-11-10), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnilab/blob/1.x/docs/policies.md)).

- Limits: 25 a second per key.
- Licence: CC-BY-4.0; commercial use: unknown.
- Credit: required: Jain A. et al., The Materials Project: A materials genome approach to accelerating materials innovation, APL Materials 1, 011002 (2013) (https://next-gen.materialsproject.org/about/cite).
- Needs: a key.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the Materials Project. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License (of this package's code): MIT.
