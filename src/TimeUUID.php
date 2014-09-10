<?php
namespace FluentCQL;
/**
 * The goods are here: www.ietf.org/rfc/rfc4122.txt.
 */
class TimeUUID {
	// A grand day! 100's nanoseconds precision at 00:00:00.000 15 Oct 1582.
	protected static $_startEpoch = -122192928000000000;

	protected static $_mac;

	protected static $_lsb;

	protected static $_lastNanos = 0;

	public static function setMAC($mac)
	{
		self::$_mac = implode('', explode(':', $mac));
	}

	protected static function _unsignedRightShift($number, $amount)
	{
		if ($number >= 0)
			return $number >> $amount;
		$number >>= $amount;
		if ($amount == 1)
			return $number & 0x7fffffffffffffff;
		return $number & ((1 << (64 - $amount)) - 1);
	}

	/**
	 * 
	 * @param int $sec
	 * @param double $msec
	 * @return string
	 */
	protected static function _createTimeHex($sec = null, $msec = 0)
	{
		if (isset($sec)) {
			$nanos = (int)$sec * 10000000 + (int)($msec * 10000000);
		}
		else {
			list($msec, $sec) = explode(' ', microtime());
			$nanos = (int)($sec . substr($msec, 2, 7));
		}
		
		$nanosSince = $nanos - self::$_startEpoch;

		if ($nanosSince > self::$_lastNanos)
			self::$_lastNanos = $nanosSince;
		else
			$nanosSince = ++self::$_lastNanos;

		$msb = 0;
		$msb |= (0x00000000ffffffff & $nanosSince) << 32;
		$msb |= self::_unsignedRightShift(0x0000ffff00000000 & $nanosSince, 16);
		$msb |= self::_unsignedRightShift(0xffff000000000000 & $nanosSince, 48);
		$msb |= 0x0000000000001000; // sets the version to 1.

		$timeHex = str_pad(dechex($msb), 16, '0', STR_PAD_LEFT);
		return substr($timeHex, 0, 8) . '-' . substr($timeHex, 8, 4) . '-' . substr($timeHex, 12, 4);
	}

	protected static function _makeClockSeq()
	{
		$lsb = 0;
		$lsb |= 0x8000; // variant (2 bits)
		$lsb |= mt_rand(0, (1 << 14) - 1); // clock sequence (14 bits)

		return dechex($lsb);
	}

	public static function getTimeUUID($sec = null, $msec = 0)
	{
		if (self::$_lsb === null)
			self::$_lsb = self::_makeClockSeq();
		return self::_createTimeHex($sec, $msec) . '-' . self::$_lsb . '-' . self::$_mac;
	}
}
