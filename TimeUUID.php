<?php
namespace FluentCQL;
/**
 * The goods are here: www.ietf.org/rfc/rfc4122.txt.
 */
class TimeUUID {
	// A grand day! 100's nanoseconds precision at 00:00:00.000 15 Oct 1582.
	protected static $_startEpoch = -122192928000000000;

	protected static function _unsignedRightShift($number, $amount)
	{
		if ($number >= 0)
			return $number >> $amount;
		$number >>= $amount;
		if ($amount == 1)
			return $number & 0x7fffffffffffffff;
		return $number & ((1 << (64 - $amount)) - 1);
	}

	protected static function _createTimeHex($timestamp)
	{
		if (isset($timestamp)) {
			$nanos = $timestamp * 10000000;
		}
		else {
			$timeArray = explode(' ', microtime());
			$nanos = (int)($timeArray[1] . substr($timeArray[0], 2, -1));
		}
		$nanosSince = $nanos - self::$_startEpoch;

		$msb = 0;
		$msb |= (0x00000000ffffffff & $nanosSince) << 32;
		$msb |= self::_unsignedRightShift(0x0000ffff00000000 & $nanosSince, 16);
		$msb |= self::_unsignedRightShift(0xffff000000000000 & $nanosSince, 48);
		$msb |= 0x0000000000001000; // sets the version to 1.

		$timeHex = str_pad(dechex($msb), 16, '0', STR_PAD_LEFT);
		return substr($timeHex, 0, 8) . '-' . substr($timeHex, 8, 4) . '-' . substr($timeHex, 12, 4);
	}

	protected static function _makeNode($ip)
	{
		$node = 0;
		$hash = md5($ip, true);
		for ($i = 0; $i < 6; ++$i)
		{
			//left shift (5 - $i) octets
			$node |= unpack('C', substr($hash, $i, 1))[1] << ((5 - $i) << 3);
		}
		// Since we don't use the mac address, the spec says that multicast
		// bit (least significant bit of the first octet of the node ID) must be 1.
		return $node | 0x0000010000000000;
	}

	protected static function _makeClockSeqAndNode($ip)
	{
		$lsb = 0;
		$lsb |= 0x8000000000000000; // variant (2 bits)
		$lsb |= mt_rand(0, (1 << 14) - 1) << 48; // clock sequence (14 bits)
		$lsb |= self::_makeNode($ip);

		$hex = dechex($lsb);
		return substr($hex, 0, 4) . '-' . substr($hex, 4);
	}

	public static function getTimeUUID($ip, $timestamp = null)
	{
		return self::_createTimeHex($timestamp) . '-' . self::_makeClockSeqAndNode($ip);
	}
}
