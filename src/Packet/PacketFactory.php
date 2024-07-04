<?php

namespace gipfl\Protocol\EspaX\Packet;

use gipfl\Protocol\EspaX\Enum\RootTypePrefix;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SimpleXMLElement;

class PacketFactory
{
    public static function packetFromSimpleXml(SimpleXMLElement $xml, LoggerInterface $logger)
    {
        // TODO: verify, whether the validator checks for "one element only"
        foreach ($xml as $type => $element) {
            switch (substr($type, 0, 4)) {
                case RootTypePrefix::REQUEST:
                    return EspaXRequest::fromSimpleXml($type, $element);
                case RootTypePrefix::RESPONSE:
                    return EspaXResponse::fromSimpleXml($type, $element);
                case RootTypePrefix::INDICATION:
                    return EspaXIndication::fromSimpleXml($type, $element);
                case RootTypePrefix::COMMAND:
                    return EspaXCommand::fromSimpleXml($type, $element);
                case RootTypePrefix::PROPRIETARY:
                    $logger->warning('Got a proprietary request, not supported');
                    return null;
                default:
                    // TODO: ProtcolError?
                    throw new RuntimeException("Got an unknown ESPA-X Packet: $type");
            }
        }

        throw new RuntimeException('Got an ESPA-X Packet w/o root element');
    }
}
