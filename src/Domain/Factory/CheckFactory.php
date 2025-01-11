<?php

namespace OpenCCK\Domain\Factory;

use Amp\Process\ProcessException;
use OpenCCK\Domain\Entity\Check;

class CheckFactory {
    /**
     * @param string $site Name of portal
     * @param string $name Name of check
     * @param string $type Type of portal check
     * @param array $params Parameters of check
     * @return Check
     *
     * @throws ProcessException
     */
    static function create(string $site, string $name, string $type, array $params): Check {
        return new Check($site, $name, $type, $params);
    }
}
