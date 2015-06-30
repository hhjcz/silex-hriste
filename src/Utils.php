<?php

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class Utils {

	/**
	 * @param $timestamp
	 * @return Carbon\Carbon
	 */
	public static function prettifyTimestamp($timestamp)
	{
		$carbon = Carbon\Carbon::createFromFormat('Y-m-d\TG:i:s\+0000', $timestamp, 'UTC');
		$carbon->setTimezone('Europe/Prague');
		setlocale(LC_TIME, 'cs_CZ');

		return $carbon;
	}

}