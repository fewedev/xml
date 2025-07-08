<?php

declare(strict_types=1);

namespace FeWeDev\Xml;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Files;
use FeWeDev\Base\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Writer
{
    /** Define regular expression to identify data that needs CDATA */
    public const CDATA_REGEX = '/[^a-zA-Z0-9-_.,:;# \/]/';

    /** @var Files */
    protected $files;

    /** @var Arrays */
    protected $arrays;

    /** @var Variables */
    protected $variables;

    /** @var string */
    private $basePath = './';

    /** @var string */
    private $fileName;

    /** @var int */
    private $flushCounter = 0;

    /** @var array<int, string> */
    private $forceCharacterData = [];

    public function __construct(Files $files, Arrays $arrays, Variables $variables)
    {
        $this->files = $files;
        $this->arrays = $arrays;
        $this->variables = $variables;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    /**
     * @return array<int, string>
     */
    public function getForceCharacterData(): array
    {
        return $this->forceCharacterData;
    }

    public function addForceCharacterData(string $elementName): void
    {
        $this->forceCharacterData[] = $elementName;
    }

    /**
     * @param array<string, mixed> $rootElementAttributes
     * @param array<string, mixed> $data
     *
     * @throws \Exception
     */
    public function write(
        string $rootElement,
        array $rootElementAttributes,
        array $data,
        bool $append = false,
        string $version = '1.0',
        string $encoding = 'UTF-8'
    ): void {
        $fileName = $this->files->determineFilePath($this->getFileName(), $this->getBasePath());

        if (!$append && file_exists($fileName)) {
            unlink($fileName);
        }

        $this->files->createDirectory(\dirname($fileName));

        $xmlWriter = new \XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);
        $xmlWriter->setIndentString('  ');
        $xmlWriter->startDocument($version, $encoding);

        $xmlWriter->startElement($rootElement);

        foreach ($rootElementAttributes as $rootElementAttributeName => $rootElementAttributeValue) {
            $xmlWriter->writeAttribute(
                $rootElementAttributeName,
                $this->variables->stringValue($rootElementAttributeValue)
            );
        }

        file_put_contents($fileName, $xmlWriter->flush());

        $this->flushCounter = 0;

        foreach ($data as $key => $value) {
            $this->addElement($xmlWriter, $key, $value);
        }

        $xmlWriter->endElement();

        file_put_contents($fileName, $xmlWriter->flush(), \FILE_APPEND);
    }

    /**
     * @param array<string, mixed> $rootElementAttributes
     * @param array<string, mixed> $data
     *
     * @throws \Exception
     */
    public function output(
        string $rootElement,
        array $rootElementAttributes,
        array $data,
        string $version = '1.0',
        string $encoding = 'UTF-8'
    ): string {
        $xmlWriter = new \XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);
        $xmlWriter->setIndentString('  ');
        $xmlWriter->startDocument($version, $encoding);

        $xmlWriter->startElement($rootElement);

        foreach ($rootElementAttributes as $rootElementAttributeName => $rootElementAttributeValue) {
            $xmlWriter->writeAttribute(
                $rootElementAttributeName,
                $this->variables->stringValue($rootElementAttributeValue)
            );
        }

        $output = $xmlWriter->flush();

        $this->flushCounter = 0;

        foreach ($data as $key => $value) {
            $this->addElement($xmlWriter, $key, $value);
        }

        $xmlWriter->endElement();

        $output .= $xmlWriter->flush();

        return $output;
    }

    /**
     * Add xml-node with optional data.
     *
     * @param array<string, mixed> $attributes
     * @param mixed|null           $data
     *
     * @throws \Exception
     */
    protected function addElement(\XMLWriter $xmlWriter, string $name, $data = null, array $attributes = []): void
    {
        if (\is_array($data)) {
            if ($this->arrays->isAssociative($data)) {
                $xmlWriter->startElement($name);
                foreach ($attributes as $attributeName => $attributeValue) {
                    $xmlWriter->writeAttribute($attributeName, $this->variables->stringValue($attributeValue));
                }
                $dataAttributes = [];
                foreach ($data as $key => $value) {
                    if (preg_match('/^@/', $key)) {
                        unset($data[$key]);
                        $xmlWriter->writeAttribute(substr($key, 1), $this->variables->stringValue($value));
                    }
                }
                foreach ($data as $key => $value) {
                    $this->addElement($xmlWriter, $key, $value, $dataAttributes);
                }
                $xmlWriter->endElement();
            } else {
                foreach ($data as $value) {
                    $this->addElement($xmlWriter, $name, $value, $attributes);
                }
            }
        } else {
            $this->writeData($xmlWriter, $name, $this->variables->stringValue($data));
        }
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws \Exception
     */
    protected function writeData(\XMLWriter $xmlWriter, string $name, string $data, array $attributes = []): void
    {
        $isCharacterData = \in_array($name, $this->forceCharacterData);

        if ($isCharacterData || preg_match(static::CDATA_REGEX, $data)) {
            $xmlWriter->startElement($name);
            $xmlWriter->writeCdata($this->encode($data));
            $xmlWriter->endElement();
        } else {
            if (empty($attributes)) {
                $xmlWriter->writeElement($name, $this->encode($data));
            } else {
                $xmlWriter->startElement($name);
                foreach ($attributes as $attributeName => $attributeValue) {
                    $xmlWriter->writeAttribute($attributeName, $this->variables->stringValue($attributeValue));
                }
                $xmlWriter->text($data);
                $xmlWriter->endElement();
            }
        }

        ++$this->flushCounter;

        if (1000 === $this->flushCounter) {
            file_put_contents($this->getFileName(), $xmlWriter->flush(), \FILE_APPEND);

            $this->flushCounter = 0;
        }
    }

    /**
     * @throws \Exception
     */
    protected function encode(string $text, string $charset = 'UTF-8'): string
    {
        if (\function_exists('iconv') && \function_exists('mb_detect_encoding') && \function_exists('mb_detect_order')) {
            $order = mb_detect_order();

            if (\is_bool($order)) {
                $order = null;
            }

            $detectedEncoding = mb_detect_encoding($text, $order, true);

            if (false === $detectedEncoding) {
                throw new \Exception('Could not detect encoding of text.');
            }

            $text = iconv($detectedEncoding, $charset, $text);

            if (false === $text) {
                throw new \Exception('Could not encode text.');
            }
        }

        return $text;
    }
}
