<?php

namespace OpenCCK\Domain\Entity;

final class Site {
    /**
     * @param string $name Name of portal
     * @param Check[] $checks Checks of portal
     */
    public function __construct(public string $name, public array $checks = []) {
    }
}
