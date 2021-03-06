<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Types;
use Clue\QDataStream\Reader;

abstract class Protocol
{
    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L30
    const MAGIC = 0x42b33f00;

    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L32
    const TYPE_INTERNAL = 0x00;
    const TYPE_LEGACY = 0x01;
    const TYPE_DATASTREAM = 0x02;
    const TYPELIST_END = 0x80000000;

    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L39
    const FEATURE_ENCRYPTION = 0x01;
    const FEATURE_COMPRESSION = 0x02;

    const REQUEST_INVALID = 0;
    const REQUEST_SYNC = 1;
    const REQUEST_RPCCALL = 2;
    const REQUEST_INITREQUEST = 3;
    const REQUEST_INITDATA = 4;
    const REQUEST_HEARTBEAT = 5;
    const REQUEST_HEARTBEATREPLY = 6;

    protected $binary;
    protected $types;
    protected $userTypeReader;
    protected $userTypeWriter;

    public static function createFromProbe($probe)
    {
        if ($probe & self::TYPE_DATASTREAM) {
            return new DatastreamProtocol(new Binary());
        } else {
            return new LegacyProtocol(new Binary());
        }
    }

    public function __construct(Binary $binary)
    {
        $this->binary = $binary;
        $this->types = new Types();

        $this->userTypeReader = array(
            // All required by SessionInit
            'NetworkId' => function (Reader $reader) {
                return $reader->readUInt();
            },
            'Identity' => function (Reader $reader) {
                return $reader->readQVariantMap();
            },
            'IdentityId' => function (Reader $reader) {
                return $reader->readUInt();
            },
            'BufferInfo' => function (Reader $reader) {
                return array(
                    'id'      => $reader->readUInt(),
                    'network' => $reader->readUInt(),
                    'type'    => $reader->readUShort(),
                    'group'   => $reader->readUInt(),
                    'name'    => $reader->readQByteArray(),
                );
            },
            // all required by "Network" InitRequest
            'Network::Server' => function (Reader $reader) {
                return $reader->readQVariantMap();
            },
            // unknown source?
            'BufferId' => function(Reader $reader) {
                return $reader->readUInt();
            },
            'Message' => function (Reader $reader) {
                // create DateTime object with local time zone from given unix timestamp
                $datetime = function ($timestamp) {
                    $d = new \DateTime('@' . $timestamp);
                    $d->setTimeZone(new \DateTimeZone(date_default_timezone_get()));
                    return $d;
                };
                return array(
                    'id'         => $reader->readUInt(),
                    'timestamp'  => $datetime($reader->readUInt()),
                    'type'       => $reader->readUInt(),
                    'flags'      => $reader->readUChar(),
                    'bufferInfo' => $reader->readQUserTypeByName('BufferInfo'),
                    'sender'     => $reader->readQByteArray(),
                    'content'    => $reader->readQByteArray()
                );
            },
            'MsgId' => function (Reader $reader) {
                return $reader->readUInt();
            }
        );

        $this->userTypeWriter = array(
            'BufferInfo' => function ($data, Writer $writer) {
                $writer->writeUInt($data['id']);
                $writer->writeUInt($data['network']);
                $writer->writeUShort($data['type']);
                $writer->writeUInt($data['group']);
                $writer->writeQByteArray($data['name']);
            },
            'BufferId' => function ($data, Writer $writer) {
                $writer->writeUInt($data);
            },
            'MsgId' => function ($data, Writer $writer) {
                $writer->writeUInt($data);
            }
        );
    }

    /**
     * Returns whether this instance encode/decodes for the old legacy protcol
     *
     * @return boolean
     */
    abstract public function isLegacy();

    /**
     * encode the given list of values
     *
     * @param mixed[]|array<mixed> $list
     * @return string binary packet contents
     */
    abstract public function writeVariantList(array $list);

    /**
     * encode the given map of key/value-pairs
     *
     * @param mixed[]|array<mixed> $map
     * @return string binary packet contents
     */
    abstract public function writeVariantMap(array $map);

    /**
     * decodes the given packet contents and returns its representation in PHP
     *
     * @param string $packet bianry packet contents
     * @return mixed[]|array<mixed> list of values or map of key/value-pairs
     */
    abstract public function readVariant($packet);
}
