<?php

/**
 * MRPacket
 * The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * 
 * @version 1.0.0
 * @link https://www.mrpacket.de/api
 * @license GPLv2
 * @author MRPacket <info@mrpacket.de>
 * 
 * Copyright (c) 2024 MRPacket
 */
?>
<?php

namespace MRPacketForWoo\Enums;

final class OrderStatus
{
    const CANCELLED = 0;
    const PENDING = 1;
    const ARCHIVED = 2;
    const SYNCED = 3;

    public static function getValues($onlyValues = false): array
    {
        if ($onlyValues) {
            return [self::CANCELLED, self::PENDING, self::ARCHIVED, self::SYNCED];
        }

        return [
            'Storniert' => self::CANCELLED,
            'Ausstehend' => self::PENDING,
            'Archiviert' => self::ARCHIVED,
            'Synchronisiert' => self::SYNCED,
        ];
    }

    public static function getKeys()
    {
        return array_flip(self::getValues());
    }
}
