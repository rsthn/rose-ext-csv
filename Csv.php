<?php
/*
**	Rose\Ext\Wind\Csv
**
**	Copyright (c) 2021-2022, RedStar Technologies, All rights reserved.
**	https://rsthn.com/
**
**	THIS LIBRARY IS PROVIDED BY REDSTAR TECHNOLOGIES "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
**	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A 
**	PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL REDSTAR TECHNOLOGIES BE LIABLE FOR ANY
**	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT 
**	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
**	OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
**	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
**	USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace Rose\Ext\Wind;

use Rose\Errors\Error;
use Rose\IO\Path;
use Rose\Expr;
use Rose\Resources;
use Rose\Map;
use Rose\Arry;
use Rose\Text;

/**
 * Helper class.
 */
class CsvUtils
{
	/**
	 * CSV data buffer (string).
	 */
	public static $csvData = null;

	/**
	 * CSV header (Arry).
	 */
	public static $csvHeader = null;

	/**
	 * Number of rows added.
	 */
	public static $csvRowCount = 0;

	/**
	 * Separator.
	 */
	public static $csvSeparator = ',';

	/**
	 * Indicates if escaping of values is enabled.
	 */
	public static $csvEscape = true;

	/**
	 * Clears the current CSV buffer.
	 */
	public static function clear ()
	{
		self::$csvData = '';
		self::$csvHeader = null;
		self::$csvRowCount = 0;
		self::$csvSeparator = ',';
		self::$csvEscape = true;
	}

	/**
	 * Escapes a value. If the respective field in the header starts with equal-sign (=), that symbol will be prepended to the result.
	 */
	public static function escape ($value, $header=null)
	{
		if (!self::$csvEscape)
			return $value;

		$prefix = '';

		if ($header && $header[0] === '=')
			$prefix = '=';

		return $prefix . '"' . str_replace('"', '""', $value) . '"';
	}

	/**
	 * Adds a row of data to the CSV buffer.
	 */
	public static function row ($array, $header=null, $isHeader=false)
	{
		if (self::$csvData === null)
			self::$csvData = '';

		$data = array();

		$i = 0;

		foreach ($array->__nativeArray as $item)
		{
			$item = Text::trim((string)$item);

			if ($isHeader && $item[0] == '=')
				$item = substr($item, 1);

			$data[] = self::escape($item, $header ? $header->get($i++) : null);
		}

		self::$csvData .= implode(self::$csvSeparator, $data) . "\r\n";
		self::$csvRowCount++;
	}

	/**
	 * Loads a line of data from the given file handle.
	 */
	public static function parseLine ($fp)
	{
		$state = 0;
		$str = '';

		while (true)
		{
			$ch = fgetc($fp);

			if ($ch === false)
				return $str == '' ? false : $str;

			if ($state == 0 && $ch == "\n")
				break;

			switch ($state)
			{
				case 0:
					if ($ch == '"') $state = 1;
					$str .= $ch;
					break;

				case 1:
					if ($ch == '"') $state = 2;
					$str .= $ch;
					break;

				case 2:
					if ($ch == '"') $state = 1; else $state = 0;
					$str .= $ch;
					break;
			}
		}

		$str = str_replace("\xEF\xBB\xBF", '', $str);
		return $str;
	}

	/**
	 * Parses a CSV line (string) and returns the respective columns.
	 */
	public static function parseColumns ($str, $delim, $removeQuotes)
	{
		$i = 0; $st = 0; $state = 0; $count = 0;
		$stack = array();

		$str = str_replace("\r", '', Text::trim($str)) . "\n";
		$str_length = strlen($str);

		for ($i = 0; $i < $str_length; $i++)
		{
			switch ($state)
			{
				case 0:
					if (ord($str[$i]) <= 32 && $str[$i] != "\n")
						break;

					if ($str[$i] == "\"") $state = 1; else $state = 3;
					$st = $i;

					if ($str[$i] == $delim || $str[$i] == "\n")
					{
						$stack[$count++] = Text::trim(substr($str, $st, $i - $st));
						$state = 0;
					}

					break;

				case 1:
					if ($str[$i] == "\"") $state = 2;
					break;

				case 2:
					if ($str[$i] == "\"")
					{
						$state = 1;
						break;
					}

					$stack[$count++] = Text::trim(substr($str, $st, $i - $st));
					$state = 4; $i--;

					break;

				case 3:
					if ($str[$i] == $delim || $str[$i] == "\n")
					{
						$stack[$count++] = Text::trim(substr($str, $st, $i - $st));
						$state = $str[$i] == $delim ? 5 : 0;
					}

					break;

				case 4:
					if ($str[$i] == $delim || $str[$i] == "\n") $state = $str[$i] == $delim ? 5 : 0;
					break;

				case 5:
					if ($str[$i] == "\n")
					{
						// Error?
						$stack[$count++] = '';
					}

					$i--; $state = 0;
					break;
			}
		}

		if ($removeQuotes)
		{
			foreach ($stack as &$column)
			{
				if (substr($column, 0, 1) == "\"")
					$column = substr($column, 1);

				if (substr($column, -1) == "\"")
					$column = substr($column, 0, strlen($column) - 1);
			}
		}

		return $stack;
	}

	/**
	 * Parses a single column with optional suffix and returns an object named "column data descriptor", with the column name `name`, the SQL
	 * data type `type`, original type name `stype`, and `format` fields.
	 * 
	 * The supported column suffixes are:
	 * 		:date					date
	 * 		:date:d/m/y				date
	 * 		:int					int(10)
	 * 		:primary				int(10) primary key auto_increment
	 * 		:numeric				decimal(12,2)
	 * 		:text					varchar(4096)
	 * 		:clean					varchar(4096)
	 * 		<default>				varchar(256)
	 */
	public static function parseColumn ($name)
	{
		$name = explode(':', $name);
		if (count($name) < 2) $name = array($name[0], 'string');

		$t = $name[0];

		switch (strtolower($name[1]))
		{
			case 'date':
				return array(
					'name' => $name[0], 'stype' => strtolower($name[1]),
					'type' => 'date',
					'format' => (count($name) > 2 ? strtolower($name[2]) : 'yyyy-mm-dd')
				);

			case 'int':
				return array(
					'name' => $name[0], 'stype' => strtolower($name[1]),
					'type' => 'int(10)'
				);

			case 'primary':
				return array(
					'name' => $name[0], 'stype' => strtolower($name[1]),
					'type' => 'int(10) primary key auto_increment'
				);

			case 'numeric':
				return array(
					'name' => $name[0], 'stype' => strtolower($name[1]),
					'type' => 'decimal(12,2)'
				);

			case 'text':
				return array(
					'name' => $name[0], 'stype' => strtolower($name[1]),
					'type' => 'varchar(4096)'
				);

			case 'clean':
				return array(
					'name' => $name[0], 'stype' => strtolower($name[1]),
					'type' => 'varchar(4096)'
				);
		}

		return array('name' => $name[0], 'stype' => 'string', 'type' => 'varchar(256)');
	}

	/**
	 * Parses a date given a date string and a format. The format string can have any character, but the
	 * following will be translated to their respective value:
	 * 		d	day
	 * 		m	month
	 * 		y	year
	 * 
	 */
	public static function parseDate ($value, $format)
	{
		$o = array('year' => '', 'month' => '', 'day' => '');
		$n = strlen($value);
		$j = 0;

		for ($i = 0; $i < $n; $i++)
		{
			switch ($format[$i+$j])
			{
				case 'd':
					if (strpos("0123456789", $value[$i]) === false)
					{
						$i--; $j++;
						break;
					}

					$o['day'] .= $value[$i];
					break;

				case 'm':
					if (strpos("0123456789", $value[$i]) === false)
					{
						$i--; $j++;
						break;
					}

					$o['month'] .= $value[$i];
					break;

				case 'y':

					if (strpos("0123456789", $value[$i]) === false)
					{
						$i--; $j++;
						break;
					}

					$o['year'] .= $value[$i];
					break;

				default:
					if ($format[$i+$j] != $value[$i])
						return null;
			}
		}

		return $o;
	}

	/**
	 * Extracts only valid characters from the given value.
	 */
	public static function extract ($val, $chars, $stop)
	{
		$n = strlen($val);
		$s = '';

		for ($i = 0; $i < $n; $i++)
		{
			if ($stop && strpos($stop, $val[$i]) !== false)
				break;

			if (strpos($chars, $val[$i]) !== false)
				$s .= $val[$i];
		}

		return $s;
	}

	/**
	 * Parses a value given the column data descriptor.
	 */
	public static function parseValue ($value, $col, $escape=true)
	{
		switch ($col['stype'])
		{
			case 'date':
				$value = self::parseDate($value, $col['format']);
				if ($value != null) $value = $value['year'].'-'.$value['month'].'-'.$value['day'];
				break;

			case 'int':
				return self::extract($value, '-+0123456789', '.');

			case 'primary':
				$value = null;
				break;

			case 'numeric':
				return self::extract($value, '-+0123456789.', null);

			case 'clean':
				$value = str_replace(",", '', str_replace(".", '', str_replace("(", '', str_replace(")", '', $value))));
				break;
		}

		if ($escape)
			return $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
		else
			return $value;
	}

	/**
	 * Loads a CSV file into a table.
	 */
	public static function loadCsv ($filename, $tablename, $temp=false, $max='all', $extra=null)
	{
		if ($max == 'all') $max = 1e6;
		$max = $max + 2;

		if ($extra) $extra = $extra->__nativeArray; else $extra = array();

		$sql = Resources::getInstance()->Database;

		if (!file_exists($filename))
			throw new Error ('File was not found: ' . $filename);

		$fp = fopen($filename, "r");
		if (!$fp) throw new Error ('Unable to open input file: ' . $filename);

		$delim = substr(strtoupper($filename), -3) == 'TSV' ? "\t" : ",";
		$row_number = -1;

		while (($line = self::parseLine($fp)) !== false)
		{
			$row_number++;

			if ($row_number == $max)
				break;

			if ($row_number == 1000000)
			{
				\Rose\trace('[ERROR] Stopping because more than 1,000,000 rows were found. FILE=' . $filename);
				break;
			}

			$line = Text::trim($line);
			if (!$line) continue;

			$tmp = Text::trim(str_replace($delim, '', $line));
			if (!strlen($tmp)) continue;

			if ($row_number == 0)
			{
				$fields = self::parseColumns ($line, $delim, true);
				foreach ($extra as $ii=>$vv) array_unshift($fields, $ii);
				$num_fields = count($fields);

				//$sql->execQuery ('DROP TABLE IF EXISTS '.$tablename);

				if ($temp === true)
					$query = 'CREATE TEMPORARY TABLE '.$tablename.' (';
				else
					$query = 'CREATE TABLE IF NOT EXISTS '.$tablename.' (';

				$rfields = array();

				foreach ($fields as $col)
				{
					$rfield = self::parseColumn ($col);

					if ($rfield['name'][0] == '-')
						$rfield['name'] = '';

					$rfields[] = $rfield;
					if (!$rfield['name']) continue;

					$query .= '`'.$rfield['name'].'` '.$rfield['type'].' default null';
					$query .= ',' . "\n";
				}

				$query = substr($query, 0, -2) . ')';

				try {
					$sql->execQuery ($query);
				}
				catch (Exception $e) {
					fclose ($fp);
					throw new Error ("Unable to create table. Please check your CSV header fields: " . $e->getMessage());
				}

				continue;
			}

			$cols = self::parseColumns ($line, $delim, true);
			foreach ($extra as $ii=>$vv) array_unshift($cols, $vv);

			$num_cols = count($cols);
			$num_def = count($rfields);
			$num_empty = 0;

			for ($j = 0; $j < $num_cols; $j++)
				if ($cols[$j] == '') $num_empty++;

			if ($num_cols == $num_empty)
				continue;

			$query = 'INSERT INTO '.$tablename.' (';

			for ($j = 0; $j < $num_cols; $j++)
			{
				if ($j >= $num_def || !$rfields[$j]['name']) continue;

				$query .= "`" . $rfields[$j]['name'] . "`";
				$query .= ',';
			}

			$query = substr($query, 0, -1) . ') VALUES(';

			for ($j = 0; $j < $num_cols; $j++)
			{
				if ($j >= $num_def || !$rfields[$j]['name']) continue;

				$query .= self::parseValue ($cols[$j], $rfields[$j]);
				$query .= ',';
			}

			$query = substr($query, 0, -1) . ')';

			try {
				$sql->execQuery ($query);
			}
			catch (Exception $e) {
				fclose ($fp);
				throw new Error ('Unable to insert row #'.($row_number+1).', please check your CSV data: '.$e->getMessage());
			}
		}

		if ($row_number == -1)
		{
			fclose ($fp);
			throw new Error ('Input file does not have any data.');
		}

		fclose ($fp);
	}

	/**
	 * Loads a CSV file into memory, returns an Arry<Map>.
	 */
	public static function readCsv ($filename)
	{
		if (!file_exists($filename))
			throw new Error ('File was not found: ' . $filename);

		$fp = fopen($filename, "r");
		if (!$fp) throw new Error ('Unable to open input file: ' . $filename);

		$delim = substr(strtoupper($filename), -3) == 'TSV' ? "\t" : ",";
		$row_number = -1;

		$list = new Arry();

		while (($line = self::parseLine($fp)) !== false)
		{
			$row_number++;

			if ($row_number == 1000000)
			{
				\Rose\trace('[ERROR] Stopping because more than 1,000,000 rows were found. FILE=' . $filename);
				break;
			}

			$line = Text::trim($line);
			if (!$line) continue;

			$tmp = Text::trim(str_replace($delim, '', $line));
			if (!strlen($tmp)) continue;

			if ($row_number == 0)
			{
				$fields = self::parseColumns ($line, $delim, true);
				$num_fields = count($fields);

				$rfields = array();

				foreach ($fields as $col)
				{
					$rfield = self::parseColumn ($col);

					if ($rfield['name'][0] == '-')
						$rfield['name'] = '';

					$rfields[] = $rfield;
				}

				continue;
			}

			$cols = self::parseColumns ($line, $delim, true);

			$num_cols = count($cols);
			$num_def = count($rfields);
			$num_empty = 0;

			for ($j = 0; $j < $num_cols; $j++)
				if ($cols[$j] == '') $num_empty++;

			if ($num_cols == $num_empty)
				continue;

			$o = new Map();

			for ($j = 0; $j < $num_cols; $j++)
			{
				if ($j >= $num_def || !$rfields[$j]['name']) continue;

				$o->set($rfields[$j]['name'], self::parseValue ($cols[$j], $rfields[$j], false));
			}

			$list->push($o);
		}

		fclose ($fp);
		return $list;
	}

};

/**
 * Expression functions.
 */

 /**
  * csv::load filename:string tableName:string [extraFields:Map]
  */
Expr::register('csv::load', function($args, $parts, $data)
{
	CsvUtils::loadCsv ($args->get(1), $args->get(2), false, 'all', $args->length > 3 ? $args->get(3) : null);
});

 /**
  * csv::loadTemp filename:string tableName:string [extraFields:Map]
  */
Expr::register('csv::loadTemp', function($args, $parts, $data)
{
	CsvUtils::loadCsv ($args->get(1), $args->get(2), true, 'all', $args->length > 3 ? $args->get(3) : null);
});

/**
 * csv::read filename:string
 */
Expr::register('csv::read', function($args, $parts, $data)
{
	return CsvUtils::readCsv ($args->get(1));
});

/**
 * csv::clear
 */
Expr::register('csv::clear', function($args, $parts, $data)
{
	CsvUtils::clear();
});

/**
 * csv::separator
 */
Expr::register('csv::separator', function($args, $parts, $data)
{
	CsvUtils::$csvSeparator = $args->get(1);
});

/**
 * csv::escape
 */
Expr::register('csv::escape', function($args, $parts, $data)
{
	CsvUtils::$csvEscape = \Rose\bool($args->get(1));
});

/**
 * csv::rowCount
 */
Expr::register('csv::rowCount', function($args, $parts, $data)
{
	return CsvUtils::$csvRowCount;
});

/**
 * csv::header columNames:Arry
 */
Expr::register('csv::header', function($args, $parts, $data)
{
	CsvUtils::clear();
	CsvUtils::$csvHeader = $args->get(1);
	CsvUtils::row (CsvUtils::$csvHeader, null, true);
});

/**
 * csv::row values:Map|Arry
 */
Expr::register('csv::row', function($args, $parts, $data)
{
	CsvUtils::row ($args->get(1), CsvUtils::$csvHeader);
});

/**
 * csv::rows rows:Arry<Arry|Map>
 */
Expr::register('csv::rows', function($args, $parts, $data)
{
	$args->get(1)->forEach(function ($row)
	{
		CsvUtils::row ($row, CsvUtils::$csvHeader);
	});
});

/**
 * csv::data [clear:boolean]
 */
Expr::register('csv::data', function($args, $parts, $data)
{
	$data = CsvUtils::$csvData;
	if ($args->has(1) && $args->get(1) === true)
		CsvUtils::clear();

	return $data;
});

/**
 * csv::dump filename:string [disposition:string]
 */
Expr::register('csv::dump', function($args, $parts, $data)
{
	header("Content-Type: text/csv");
	header("Content-Disposition: ".($args->has(2) ? $args->get(2) : 'inline')."; filename=\"".$args->get(1)."\"");

	echo (b"\xEF\xBB\xBF" . CsvUtils::$csvData);
	CsvUtils::$csvData = null;

	exit();
});
