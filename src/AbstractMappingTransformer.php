<?php

declare(strict_types=1);

namespace Valouleloup\ArrayMapping;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

abstract class AbstractMappingTransformer
{
    /**
     * @var PropertyAccessor
     */
    private $accessor;

    public function __construct()
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Parse input array to output array according to a YAML config file
     *
     * @param array $data The input array to transform
     * @return array The output array (input array transformed)
     */
    abstract protected function transform(array $data);

    /**
     * @param array $mapping
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function transformFromMapping(array $mapping, array $data)
    {
        $result = [];

        foreach ($mapping as $key) {
            $keyValue = $this->getKeyValue($key, $data);

            if (isset($key['required']) && true === $key['required'] && null === $keyValue) {
                throw new \Exception('Field ' . $key['from'] . ' required.');
            }

            if (isset($key['dependencies'])) {
                $dependenciesExist = true;

                foreach ($key['dependencies'] as $dependency) {
                    if (null === $this->getValue($result, $mapping[$dependency]['to'])) {
                        $dependenciesExist = false;
                    }
                }

                if ($dependenciesExist && null === $keyValue) {
                    throw new \Exception('Field ' . $key['from'] . ' required if dependencies true.');
                }
            }

            if (null !== $keyValue) {
                $this->accessor->setValue($result, $this->convertToBrackets($key['to']), $keyValue);
            }
        }

        return $result;
    }

    /**
     * @param array $key
     * @param array $data
     *
     * @return mixed
     */
    private function getKeyValue(array $key, array $data)
    {
        //1. Get function value if is set so
        //2. Else get raw value is no function is defined
        //3. If keyValue is null and a default value is set, return default value
        $keyValue = null;

        if (isset($key['function'])) {
            $params = [];

            foreach ($key['function']['params'] as $param) {
                $params[] = $this->getValue($data, $param);
            }

            $keyValue = call_user_func_array([$this, $key['function']['name']], $params);
        } else {
            if (isset($key['from'])) {
                $keyValue = $this->getValue($data, $key['from']);
            }
        }

        if (null === $keyValue && isset($key['default'])) {
            $keyValue = $key['default'];
        }

        return $keyValue;
    }

    /**
     * @param array $data
     * @param string $key
     *
     * @return mixed
     */
    private function getValue(array $data, string $key)
    {
        return $this->accessor->getValue($data, $this->convertToBrackets($key));
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function convertToBrackets(string $path)
    {
        $keys = explode('.', $path);

        $bracketPath = '';

        foreach ($keys as $key) {
            $bracketPath .= '[' . $key . ']';
        }

        return $bracketPath;
    }
}
