<?php

namespace OpenCCK\App\Service;

use OpenCCK\Domain\Entity\Site;
use OpenCCK\Domain\Factory\SiteFactory;
use OpenCCK\Infrastructure\API\App;

use Exception;
use Monolog\Logger;
use Revolt\EventLoop;
use function Amp\delay;

class WebMonitorService {
    private static WebMonitorService $_instance;
    private ?Logger $logger;

    /**
     * @var array<string, Site>
     */
    public array $sites = [];

    /**
     * @throws Exception
     */
    private function __construct(Logger $logger = null) {
        $this->logger = $logger ?? App::getLogger();

        $dir = PATH_ROOT . '/config/';
        if (!is_dir($dir)) {
            throw new Exception('config directory not found');
        }

        foreach (scandir($dir) as $item) {
            if (in_array($item, ['.', '..'])) {
                continue;
            }
            $path = $dir . $item;
            if (is_file($path)) {
                $this->loadConfig($path);
            }
        }
    }

    /**
     * @param ?Logger $logger
     * @return WebMonitorService
     * @throws Exception
     */
    public static function getInstance(Logger $logger = null): WebMonitorService {
        return self::$_instance ??= new self($logger);
    }

    /**
     * @param string $path
     * @return void
     */
    private function loadConfig(string $path): void {
        if (str_ends_with($path, '.json')) {
            $parts = explode('/', $path);
            $filename = array_pop($parts);
            $name = substr($filename, 0, -5);
            $config = json_decode(file_get_contents($path));

            if (isset($this->sites[$name])) {
                $this->logger->error(sprintf('Site config "%s" already exists', $name));
                delay(5);
                exit();
            }

            EventLoop::queue(function () use ($name, $config) {
                $this->sites[$name] = SiteFactory::create($name, $config);
                $this->logger->notice(sprintf('Site config "%s" loaded', $name));
            });
        }
    }
}
