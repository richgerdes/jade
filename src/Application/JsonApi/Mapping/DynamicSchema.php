<?php

/*
 * Copyright (c) 2017-present trivago GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Trivago\Jade\Application\JsonApi\Mapping;

use Neomerx\JsonApi\Contracts\Document\LinkInterface;
use Trivago\Jade\Application\JsonApi\Config\ResourceConfig;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Contracts\Schema\DocumentInterface;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Schema\BaseSchema;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Trivago\Jade\Domain\Mapping\Property\EmbeddedProperty;
use Trivago\Jade\Domain\Mapping\Property\Property;
use Trivago\Jade\Domain\Mapping\ResourceMapper;
use Trivago\Jade\Domain\Mapping\Value;
use Trivago\Jade\Domain\ResourceManager\Exception\InvalidModelPath;

class DynamicSchema extends BaseSchema
{
    /**
     * @var ResourceConfig
     */
    private $resourceConfig;

    /**
     * @var array
     */
    private $requestedRelationships;

    /**
     * @var ResourceMapper
     */
    private $resourceMapper;

    /**
     * @var string
     */
    private $urlPrefix;

    /**
     * @param FactoryInterface $factory
     * @param ResourceConfig   $resourceConfig
     * @param ResourceMapper   $resourceMapper
     * @param array            $requestedRelationships
     * @param string           $urlPrefix
     */
    public function __construct(
        FactoryInterface $factory,
        ResourceConfig $resourceConfig,
        ResourceMapper $resourceMapper,
        array $requestedRelationships,
        $urlPrefix
    ) {
        $this->resourceConfig = $resourceConfig;
        $this->resourceType = $resourceConfig->getName();
        $this->resourceMapper = $resourceMapper;
        $this->requestedRelationships = $requestedRelationships;
        $this->urlPrefix = $urlPrefix;
        parent::__construct($factory);
    }

    /**
     * @param array $requestedRelationships
     */
    public function setRequestedRelationships(array $requestedRelationships)
    {
        $this->requestedRelationships = $requestedRelationships;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string {
      return $this->resourceConfig->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getId($resource): ?string
    {
        return $resource->getId();
    }

    /**
     * Get resource attributes.
     *
     * @param object $resource
     *
     * @return array
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $resourceMapping = $this->resourceMapper->getResourceMapping($this->resourceConfig->getName(), $resource);
        $attributes = [];
        foreach ($resourceMapping->getProperties() as $property) {
            $value = $this->getPropertyValue($resource, $property);

            if ($property instanceof EmbeddedProperty) {
              $embedded = $value->getValue();

              if (is_object($embedded)) {
                $embedded_reflection = new \ReflectionClass($embedded);

                $embedded_value = [];
                foreach ($embedded_reflection->getProperties() as $embedded_property) {
                  $embedded_value[$embedded_property->getName()] = $propertyAccessor->getValue($embedded, $embedded_property->getName());
                }

                $value = new Value($embedded_value);
              }
            }

            if (null !== $value) {
                if (!$this->isSimpleValue($value->getValue())) {
                    continue;
                }
                $attributes[$property->getName()] = $this->getSimpleValue($value->getValue());
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSelfSubUrl($resource): string
    {
        return $this->urlPrefix.parent::getSelfSubUrl($resource);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidModelPath
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];
        $resourceMapping = $this->resourceMapper->getResourceMapping($this->resourceConfig->getName(), $resource);

        foreach ($context->getIncludePaths() as $relationshipName) {
            if (!$resourceMapping->hasRelationship($relationshipName)) {
                throw new InvalidModelPath($relationshipName);
            }

            $value = $this->getPropertyValue($resource, $resourceMapping->getRelationship($relationshipName));
            if (null !== $value) {
                $relationships[$relationshipName] = [
                    static::RELATIONSHIP_DATA => $value->getValue(),
                ];
            }
        }

        return $relationships;
    }

    /**
     * {@inheritdoc}
     */
    public function getIncludePaths()
    {
        return $this->requestedRelationships;
    }

    /**
     * {@inheritdoc}
     */
    public function getIncludedResourceLinks($resource)
    {
        return [
            LinkInterface::SELF => $this->getSelfSubLink($resource),
        ];
    }

    /**
     * @param object $resource
     * @param Property $property
     *
     * @return Value
     */
    protected function getPropertyValue($resource, Property $property)
    {
        $name = $property->getName();

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $value = $propertyAccessor->getValue($resource, $name);

        return new Value($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    protected function isSimpleValue($value)
    {
        return null === $value
            || is_scalar($value)
            || $value instanceof \DateTime
            || (is_object($value) && method_exists($value, '__toString'))
	        || is_array($value)
        ;
    }

    /**
     * @param mixed $value
     *
     * @return string|array
     */
    protected function getSimpleValue($value)
    {
    	if (null === $value || is_scalar($value)) {
            return $value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        } elseif ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        } elseif (is_array($value)) {
    		return array_map([$this, 'getSimpleValue'], $value);
	    } else {
            throw new \LogicException('Make sure you call isSimpleValue before calling this method');
        }
    }
}
