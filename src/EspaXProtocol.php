<?php

namespace gipfl\Protocol\EspaX;

use DOMDocument;
use SimpleXMLElement;

class EspaXProtocol
{
    public const PACKET_PREFIX = 'EX';
    public const ESPA_NAMESPACE = 'http://ns.espa-x.org/espa-x';
    public const ESPA_VERSION = '1.00';

    protected static $validateOnRender = true;

    public static function renderSimpleXml(SimpleXMLElement $espa)
    {
        /** @var DOMDocument $dom */
        $dom = dom_import_simplexml($espa)->ownerDocument;
        $dom->formatOutput = true;
        if (self::$validateOnRender) {
            $dom->schemaValidate(__DIR__ . '/resources/espa-x_v1.xsd');
        }

        return $dom->saveXML();
    }

    public static function validateSimpleXml(SimpleXMLElement $espa)
    {
        /** @var DOMDocument $dom */
        $dom = dom_import_simplexml($espa)->ownerDocument;
        $dom->formatOutput = true;
        $dom->schemaValidate(dirname(__DIR__) . '/lab-log/espa-x100.xsd');
    }

    public static function enableValidationOnRender(): void
    {
        self::$validateOnRender = true;
    }

    public static function disableValidationOnRender(): void
    {
        self::$validateOnRender = false;
    }
}
