<?php

namespace Trivago\Jade\Domain\Mapping\Property;

/**
 * A embedded property that is defined in the class.
 */
class EmbeddedProperty extends Property
{
    /**
     * @param string $name
     * @param string $method
     */
    public function __construct($name)
    {
        parent::__construct($name, NULL);
    }
}
