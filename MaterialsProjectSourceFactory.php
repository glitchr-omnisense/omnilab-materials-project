<?php

namespace Omnilab\MaterialsProject;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The Materials Project: computed properties of inorganic materials by
 * their id, the materials of a formula or a chemical system - an
 * account's API key required (free, on registering).
 *
 *   options:
 *     api_key: '%env(MP_API_KEY)%'     # required: the key of a Materials Project account
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     throttle: 0                      # seconds between two calls: no rate published
 *     base_uri: 'https://api.materialsproject.org/'
 */
final class MaterialsProjectSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'materials-project',
            'omnilab.required_options' => ['api_key'],
            'throttle' => 0.0,
            'base_uri' => MaterialsProjectSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new MaterialsProjectSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('api_key'),
            (string) $c->get('base_uri', MaterialsProjectSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
