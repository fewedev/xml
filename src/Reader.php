<?php

declare(strict_types=1);

namespace FeWeDev\Xml;

use Exception;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Files;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Reader
{
    /** @var Files */
    protected $files;

    /** @var Arrays */
    protected $arrays;

    /** @var SimpleXml */
    protected $simpleXml;

    /** @var string */
    private $basePath = './';

    /** @var string */
    private $fileName;

    /**
     * @param Files     $files
     * @param Arrays    $arrays
     * @param SimpleXml $simpleXml
     */
    public function __construct(Files $files, Arrays $arrays, SimpleXml $simpleXml)
    {
        $this->files = $files;
        $this->arrays = $arrays;

        $this->simpleXml = $simpleXml;
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
     * Method to read data from XML file. The retry pause has to be defined in milliseconds.
     *
     * @param bool $removeEmptyElements
     * @param int  $retries
     * @param int  $retryPause
     *
     * @return array<mixed, mixed>
     * @throws Exception
     */
    public function read(
        bool $removeEmptyElements = true,
        int $retries = 0,
        int $retryPause = 250
    ): array {
        $fileName = $this->files->determineFilePath($this->getFileName(), $this->getBasePath());

        if (is_file($fileName)) {
            $data = $this->simpleXml->simpleXmlLoadFile($fileName, $retries, $retryPause);

            if ($data === false) {
                throw new Exception('Could not load XML file.');
            } else {
                $jsonEncoded = json_encode((array) $data);

                if ($jsonEncoded === false) {
                    throw new Exception(json_last_error_msg());
                }

                $data = json_decode($jsonEncoded, true);

                if (!is_array($data)) {
                    throw new Exception('Could not decode JSON.');
                }

                if ($removeEmptyElements) {
                    $data = $this->arrays->arrayFilterRecursive($data);
                }
            }

            return $data;
        } else {
            throw new Exception(sprintf('Could not read file: %s because: Not a file', $fileName));
        }
    }
}
