<?php

namespace OpenCCK\Domain\Factory;

use Amp\Process\ProcessException;
use OpenCCK\Domain\Entity\Site;

class SiteFactory {
    /**
     * @param string $name Name of portal
     * @param array $checks Checks for portal
     * @return Site
     *
     * @throws ProcessException
     */
    static function create(string $name, array $checks): Site {
        return new Site(
            $name,
            array_map(
                fn(object $check) => CheckFactory::create($name, $check->name, $check->type, (array) $check->params),
                $checks
            )
        );
    }
}
