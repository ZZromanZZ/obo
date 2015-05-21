<?php

/**
 * This file is part of the Obo framework for application domain logic.
 * Obo framework is based on voluntary contributions from different developers.
 *
 * @link https://github.com/obophp/obo
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

namespace obo\DataType;

class ArrayDataType extends \obo\DataType\Base\DataType {

    /**
     * @return string
     */
    public function name() {
        return "array";
    }

    /**
     * @param mixed $value
     * @throws \obo\Exceptions\BadDataTypeException
     * @return void
     */
    public function validate($value, $throwException = true) {
        if (\is_array($value) OR \is_null($value)) return true;
        if ($throwException) throw new \obo\Exceptions\BadDataTypeException("Can't write  value '" . print_r($value, true) . "' of '" . \gettype($value) . "' datatype into property '" . $this->propertyInformation->name . "' in class '" . $this->propertyInformation->entityInformation->className . "' which is of 'array' datatype.");
        return false;
    }

    /**
     * @param mixed $value
     * @return array
     */
    public static function convertValue($value) {
        return \is_null($value) ? $value : (array) $value;
    }
}
