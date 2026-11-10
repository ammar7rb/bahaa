<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class LegalContentSanitizer
{
    public function clean(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,ol,ul,li,h2,h3,h4,blockquote,a[href|title],span,div,table,thead,tbody,tr,th,td');
        $config->set('URI.DisableExternalResources', true);
        return (new HTMLPurifier($config))->purify($html);
    }
}
