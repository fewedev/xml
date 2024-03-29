<?php

declare(strict_types=1);

namespace FeWeDev\Xml;

use Exception;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Files;
use FeWeDev\Base\Variables;
use XMLWriter;

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

    /**
     * @param Files $files
     * @param Arrays $arrays
     * @param Variables $variables
     */
    public function __construct(Files $files, Arrays $arrays, Variables $variables)
    {
        $this->files = $files;
        $this->arrays = $arrays;
        $this->variables = $variables;
    }

    /**
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param string $basePath
     *
     * @return void
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @param string $fileName
     *
     * @return void
     */
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

    /**
     * @param string $elementName
     *
     * @return void
     */
    public function addForceCharacterData(string $elementName): void
    {
        $this->forceCharacterData[] = $elementName;
    }

    /**
     * @param string $rootElement
     * @param array<string, mixed> $rootElementAttributes
     * @param array<string, mixed> $data
     * @param bool $append
     * @param string $version
     * @param string $encoding
     *
     * @return void
     * @throws Exception
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

        $this->files->createDirectory(dirname($fileName));

        $xmlWriter = new XMLWriter();
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

        file_put_contents($fileName, $xmlWriter->flush(), FILE_APPEND);
    }

    /**
     * Add xml-node with optional data
     *
     * @param XMLWriter $xmlWriter
     * @param string $name
     * @param mixed $data
     * @param array<string, mixed> $attributes
     *
     * @return void
     * @throws Exception
     */
    protected function addElement(XMLWriter $xmlWriter, string $name, $data = null, array $attributes = []): void
    {
        if (is_array($data)) {
            if ($this->arrays->isAssociative($data)) {
                $xmlWriter->startElement($name);
                foreach ($attributes as $attributeName => $attributeValue) {
                    $xmlWriter->writeAttribute($attributeName, $this->variables->stringValue($attributeValue));
                }
                foreach ($data as $key => $value) {
                    $this->addElement($xmlWriter, $key, $value);
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
     * @param XMLWriter $xmlWriter
     * @param string $name
     * @param string $data
     * @param array<string, mixed> $attributes
     *
     * @return void
     * @throws Exception
     */
    protected function writeData(XMLWriter $xmlWriter, string $name, string $data, array $attributes = []): void
    {
        $isCharacterData = in_array($name, $this->forceCharacterData);

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

        $this->flushCounter++;

        if ($this->flushCounter === 1000) {
            file_put_contents($this->getFileName(), $xmlWriter->flush(), FILE_APPEND);

            $this->flushCounter = 0;
        }
    }

    /**
     * @param string $text
     * @param string $charset
     *
     * @return string
     * @throws Exception
     */
    protected function encode(string $text, string $charset = 'UTF-8'): string
    {
        if (function_exists('iconv') && function_exists('mb_detect_encoding') && function_exists('mb_detect_order')) {
            $order = mb_detect_order();

            if (is_bool($order)) {
                $order = null;
            }

            $detectedEncoding = mb_detect_encoding($text, $order, true);

            if ($detectedEncoding === false) {
                throw new Exception('Could not detect encoding of text.');
            }

            $text = iconv($detectedEncoding, $charset, $text);

            if ($text === false) {
                throw new Exception('Could not encode text.');
            }
        }

        return $text;
    }

    /**
     * @param string $rootElement
     * @param array<string, mixed> $rootElementAttributes
     * @param array<string, mixed> $data
     * @param string $version
     * @param string $encoding
     *
     * @return string
     * @throws Exception
     */
    public function output(
        string $rootElement,
        array $rootElementAttributes,
        array $data,
        string $version = '1.0',
        string $encoding = 'UTF-8'
    ): string {
        $xmlWriter = new XMLWriter();
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
}
