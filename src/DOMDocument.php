<?php

declare(strict_types=1);

namespace FeWeDev\Xml;

use DOMException;
use DOMNode;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class DOMDocument
{
    /** @var Arrays */
    protected $arrays;

    /** @var Variables */
    protected $variables;

    /**
     * @param Arrays    $arrays
     * @param Variables $variables
     */
    public function __construct(Arrays $arrays, Variables $variables)
    {
        $this->arrays = $arrays;
        $this->variables = $variables;
    }

    /**
     * @param array<string, mixed> $config
     * @param \DOMDocument         $document
     * @param DOMNode              $node
     *
     * @return void
     * @throws DOMException
     */
    public function arrayToXml(array $config, \DOMDocument $document, DOMNode $node): void
    {
        if (array_key_exists('@attributes', $config)) {
            $attributes = $this->arrays->getValue($config, '@attributes', []);
            if (is_array($attributes)) {
                foreach ($attributes as $attributeName => $attributeValue) {
                    $attribute = $document->createAttribute($attributeName);
                    $attribute->value = $attributeValue;
                    $node->appendChild($attribute);
                }
            }
            unset($config['@attributes']);
            if (count($config) > 1) {
                $this->arrayToXml($config, $document, $node);
            } else {
                $values = array_values($config);
                $value = reset($values);
                if (is_scalar($value)) {
                    $value = $this->variables->stringValue($value);
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $value = $value->__toString();
                } else {
                    $value = var_export($value, true);
                }
                $node->appendChild($document->createCDATASection($value));
            }
        } else {
            foreach ($config as $key => $value) {
                if (is_array($value)) {
                    if ($this->arrays->isAssociative($value)) {
                        $subNode = $node->appendChild($document->createElement($key));
                        $this->arrayToXml($value, $document, $subNode);
                    } else {
                        foreach ($value as $valueValue) {
                            $subNode = $node->appendChild($document->createElement($key));
                            $this->arrayToXml($valueValue, $document, $subNode);
                        }
                    }
                } else {
                    $valueNode = $document->createElement($key);
                    if (is_scalar($value)) {
                        $value = $this->variables->stringValue($value);
                    } elseif (is_object($value) && method_exists($value, '__toString')) {
                        $value = $value->__toString();
                    } else {
                        $value = var_export($value, true);
                    }
                    $valueNode->appendChild($document->createCDATASection($value));
                    $node->appendChild($valueNode);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param string               $rootName
     *
     * @return string
     * @throws DOMException
     */
    public function prepareXML(array $config, string $rootName): string
    {
        $document = new \DOMDocument('1.0');

        $document->encoding = 'utf-8';
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        $rootNode = $document->appendChild($document->createElement($rootName));

        $this->arrayToXml($config, $document, $rootNode);

        $result = $document->saveXML();

        if ($result === false) {
            throw new DOMException('Could not save XML');
        }

        return $result;
    }
}
