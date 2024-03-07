<?php

declare(strict_types=1);

namespace FeWeDev\Xml;

use Exception;
use FeWeDev\Base\Variables;
use LibXMLError;
use SimpleXMLElement;
use stdClass;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class SimpleXml
{
    /** @var Variables */
    protected $variables;

    /**
     * @param Variables $variables
     */
    public function __construct(Variables $variables)
    {
        $this->variables = $variables;
    }

    /**
     * @param string $content
     *
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function simpleXmlLoadString(string $content)
    {
        $useErrors = libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        $error = null;

        if (false === $xml) {
            $errors = libxml_get_errors();

            $error = reset($errors);

            if ($error === false) {
                throw new Exception('Could not load string.');
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if ($error !== null) {
            throw new Exception($this->formatLibXmlError($error, explode("\n", $content)));
        }

        return $xml;
    }

    /**
     * @param string $fileName
     * @param int    $retries
     * @param int    $retryPause
     *
     * @return SimpleXMLElement|false
     * @throws Exception
     */
    public function simpleXmlLoadFile(
        string $fileName,
        int $retries = 0,
        int $retryPause = 250
    ) {
        $useErrors = libxml_use_internal_errors(true);

        $counter = 0;

        do {
            $counter++;

            libxml_clear_errors();
            libxml_use_internal_errors(true);

            $xml = simplexml_load_file($fileName, 'SimpleXMLElement', LIBXML_NOCDATA);

            $error = null;

            if (false === $xml) {
                $errors = libxml_get_errors();

                $error = reset($errors);

                if ($error === false) {
                    throw new Exception('Could not parse XML.');
                }
            }

            libxml_use_internal_errors($useErrors);

            if (false === $xml) {
                if ($counter > $retries) {
                    $fileContent = file($fileName, FILE_IGNORE_NEW_LINES);

                    throw new Exception(
                        sprintf(
                            'Could not read file: %s because: %s',
                            $fileName,
                            $this->variables->isEmpty($error) || $fileContent === false ? 'Could not parse XML.' :
                                $this->formatLibXmlError($error, $fileContent)
                        )
                    );
                } else {
                    usleep($retryPause * 1000);
                }
            } else {
                break;
            }
        } while (true);

        return $xml;
    }

    /**
     * @param stdClass           $error
     * @param array<int, string> $content
     *
     * @return string
     */
    protected function formatXmlError(stdClass $error, array $content): string
    {
        $return = '';

        if (array_key_exists($error->line - 1, $content)) {
            $return .= $content[$error->line - 1]."\n";
            $return .= str_repeat('-', $error->column)."^\n";
        }

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code: ";
                break;
        }

        $return .= trim($error->message)."\n  Line: $error->line"."\n  Column: $error->column";

        return $return;
    }

    /**
     * @param LibXMLError        $error
     * @param array<int, string> $content
     *
     * @return string
     */
    protected function formatLibXmlError(LibXMLError $error, array $content): string
    {
        $return = '';

        if (array_key_exists($error->line - 1, $content)) {
            $return .= $content[$error->line - 1]."\n";
            $return .= str_repeat('-', $error->column)."^\n";
        }

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code: ";
                break;
        }

        $return .= trim($error->message)."\n  Line: $error->line"."\n  Column: $error->column";

        return $return;
    }

    /**
     * @param SimpleXMLElement $xml
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public function xmlToArray(SimpleXMLElement $xml): array
    {
        $jsonEncoded = json_encode((array) $xml);

        if ($jsonEncoded === false) {
            throw new Exception('Could not convert XML to JSON.');
        }

        $result = json_decode($jsonEncoded, true);

        if (!is_array($result)) {
            throw new Exception('Could not convert XML to array.');
        }

        return $result;
    }
}
