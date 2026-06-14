<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Traits;

use Psr\Http\Message\RequestInterface;

trait ObfuscatesHeaders
{
    /**
     * Obfuscate configured request headers, e.g. the Authorization header.
     */
    protected function obfuscateHeaders(RequestInterface $request): RequestInterface
    {
        $replacement = config('http-client-global-logger.obfuscate.replacement');

        foreach (config('http-client-global-logger.obfuscate.headers') as $name) {
            if ($request->hasHeader($name)) {
                $request = $request->withHeader($name, $replacement);
            }
        }

        return $request;
    }
}
