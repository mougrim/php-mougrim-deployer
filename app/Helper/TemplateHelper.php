<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Helper;

use RuntimeException;
use function is_string;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class TemplateHelper
{
    public function processTemplateString(string $string, array $params): string
    {
        $replace = $this->prepareParams($params);

        $result = strtr($string, $replace);
        if (preg_match_all('/({{ .+? }})/', $result, $matches)) {
            $notProcessedVariables = implode(", ", $matches[1]);
            throw new RuntimeException("Not all variables processed: {$notProcessedVariables}");
        }

        return $result;
    }

    private function prepareParams(array $params): array
    {
        $params = $this->makeParamsFlatten($params);
        foreach ($params as &$value) {
            if (is_string($value)) {
                $value = strtr($value, $params);
            }
        }
        unset($value);
        return $params;
    }

    private function makeParamsFlatten(array $params, string $keyPrefix = '', array $replace = []): array
    {
        foreach ($params as $key => $value) {
            $key = $keyPrefix . $key;
            if (is_array($value)) {
                $replace = $this->makeParamsFlatten($value, $key . '.', $replace);
            } else {
                $replace["{{ {$key} }}"] = $value;
            }
        }

        return $replace;
    }
}
