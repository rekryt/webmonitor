<?php

namespace OpenCCK\App\Controller;


use Amp\Http\Server\Request;
use OpenCCK\App\Service\WebMonitorService;

class MainController extends AbstractController {
    private WebMonitorService $service;

    public function __construct(Request $request, array $headers = []) {
        parent::__construct($request, $headers);
        $this->service = WebMonitorService::getInstance();
    }

    /**
     * @return string
     */
    public function getBody(): string {
        $this->setHeaders(['content-type' => 'text/plain; version=0.0.4']);

        $result = [];
        $metrics = [];
        foreach ($this->service->sites as $site) {
            foreach ($site->checks as $check) {
                if (!isset($metrics[$check->type])) {
                    $metrics[$check->type] = [];
                }
                $metrics[$check->type][] =
                    $check->type . '{name="' . $site->name . '",label="' . $check->name . '"} ' . $check->value;
            }
        }

        foreach ($metrics as $metricName => $data) {
            $result[] = $this->getMetric($metricName, $data);
        }

        return implode('', $result);
    }

    /**
     * @param string $name
     * @param string[] $data
     * @param string $type
     * @param string $description
     * @return string
     */
    private function getMetric(string $name, array $data, string $type = 'gauge', string $description = ''): string {
        $prefix = (\OpenCCK\getEnv('SYS_METRICS_PREFIX') ?? 'webmon') . '_';
        $name = $prefix . $name;
        return implode(
            "\n",
            array_merge(
                ['# HELP ' . $name . ' ' . $description, '# TYPE ' . $name . ' ' . $type],
                array_map(fn(string $item) => $prefix . $item, $data)
            )
        ) . "\n";
    }
}
