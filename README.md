# omnilab/materials-project

> **Not tested live.** The Materials Project's API answers nothing without an account's key ("No API
> key found in request", 401, checked on 2026-10-15), and no account was created for this package.
> Its tests read answers built on the API's published OpenAPI schema (version 0.87.3), their numbers
> placeholders. Details in [docs/index.md](docs/index.md#verified-and-not).

The Materials Project (Lawrence Berkeley National Laboratory) for
[omniscient/omnilab](https://github.com/glitchr-studio/omnilab): computed properties of inorganic
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

## Terms of use

The Materials Project asks to be cited (A. Jain et al., The Materials Project: A materials genome
approach to accelerating materials innovation, APL Materials 1, 011002 (2013)) and states its
terms on its website; its API distinguishes data under a "commercial" and a "non-commercial"
licence (the `license` parameter). Read them before use. Heavy use is announced to
heavy.api.use@materialsproject.org, as the API's documentation asks.

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
