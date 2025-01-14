<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017-2019, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\TestFramework\PhpUnit\Config;

use Infection\TestFramework\PhpUnit\Config\Exception\InvalidPhpUnitXmlConfigException;
use Infection\TestFramework\PhpUnit\Config\Path\PathReplacer;

/**
 * @internal
 */
final class XmlConfigurationHelper
{
    /**
     * @var PathReplacer
     */
    private $pathReplacer;

    /**
     * @var string
     */
    private $phpUnitConfigDir;

    public function __construct(PathReplacer $pathReplacer, string $phpUnitConfigDir)
    {
        $this->pathReplacer = $pathReplacer;
        $this->phpUnitConfigDir = $phpUnitConfigDir;
    }

    public function replaceWithAbsolutePaths(\DOMXPath $xPath): void
    {
        $queries = [
            '/phpunit/@bootstrap',
            '/phpunit/testsuites/testsuite/exclude',
            '//directory',
            '//file',
        ];

        foreach ($xPath->query(implode('|', $queries)) as $node) {
            $this->pathReplacer->replaceInNode($node);
        }
    }

    public function removeExistingLoggers(\DOMDocument $dom, \DOMXPath $xPath): void
    {
        foreach ($xPath->query('/phpunit/logging') as $node) {
            $dom->documentElement->removeChild($node);
        }
    }

    public function deactivateResultCaching(\DOMXPath $xPath): void
    {
        $nodeList = $xPath->query('/phpunit/@cacheResult');

        if ($nodeList->length) {
            $nodeList[0]->nodeValue = 'false';
        } else {
            $node = $xPath->query('/phpunit')[0];
            $node->setAttribute('cacheResult', 'false');
        }
    }

    public function setStopOnFailure(\DOMXPath $xPath): void
    {
        $this->setAttributeValue(
            $xPath,
            'stopOnFailure',
            'true'
        );
    }

    public function deactivateColours(\DOMXPath $xPath): void
    {
        $this->setAttributeValue(
            $xPath,
            'colors',
            'false'
        );
    }

    public function removeExistingPrinters(\DOMDocument $dom, \DOMXPath $xPath): void
    {
        $this->removeAttribute(
            $dom,
            $xPath,
            'printerClass'
        );
    }

    public function validate(\DOMDocument $dom, \DOMXPath $xPath): bool
    {
        if ($xPath->query('/phpunit')->length === 0) {
            throw InvalidPhpUnitXmlConfigException::byRootNode();
        }

        if (!$xPath->query('namespace::xsi')->length) {
            return true;
        }

        $schema = $xPath->query('/phpunit/@xsi:noNamespaceSchemaLocation');

        $original = libxml_use_internal_errors(true);
        $schemaPath = $this->buildSchemaPath($schema[0]->nodeValue);

        if ($schema->length && !$dom->schemaValidate($schemaPath)) {
            throw InvalidPhpUnitXmlConfigException::byXsdSchema($this->getXmlErrorsString());
        }

        libxml_use_internal_errors($original);

        return true;
    }

    public function removeDefaultTestSuite(\DOMDocument $dom, \DOMXPath $xPath): void
    {
        $this->removeAttribute(
            $dom,
            $xPath,
            'defaultTestSuite'
        );
    }

    private function getXmlErrorsString(): string
    {
        $errorsString = '';
        $errors = libxml_get_errors();

        foreach ($errors as $key => $error) {
            $level = $this->getErrorLevelString($error);
            $errorsString .= sprintf('[%s] %s', $level, $error->message);

            if ($error->file) {
                $errorsString .= sprintf(' in %s (line %s, col %s)', $error->file, $error->line, $error->column);
            }

            $errorsString .= "\n";
        }

        return $errorsString;
    }

    private function buildSchemaPath(string $nodeValue): string
    {
        if ($this->phpUnitConfigDir === '' || filter_var($nodeValue, FILTER_VALIDATE_URL)) {
            return $nodeValue;
        }

        return sprintf('%s/%s', $this->phpUnitConfigDir, $nodeValue);
    }

    private function removeAttribute(\DOMDocument $dom, \DOMXPath $xPath, string $name): void
    {
        $nodeList = $xPath->query(sprintf(
            '/phpunit/@%s',
            $name
        ));

        if ($nodeList->length) {
            $dom->documentElement->removeAttribute($name);
        }
    }

    private function setAttributeValue(\DOMXPath $xPath, string $name, string $value): void
    {
        $nodeList = $xPath->query(sprintf(
            '/phpunit/@%s',
            $name
        ));

        if ($nodeList->length) {
            $nodeList[0]->nodeValue = $value;
        } else {
            $node = $xPath->query('/phpunit')[0];
            $node->setAttribute($name, $value);
        }
    }

    private function getErrorLevelString(\LibXMLError $error): string
    {
        if ($error->level === LIBXML_ERR_WARNING) {
            return 'Warning';
        }

        if ($error->level === LIBXML_ERR_ERROR) {
            return 'Error';
        }

        return 'Fatal';
    }
}
