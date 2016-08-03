#!/usr/bin/php
<?php
/**
 * Gibt den Offset der ersten Bar einer MetaTrader-Historydatei zurück, die am oder nach dem angegebenen Zeitpunkt beginnt
 * oder -1, wenn keine solche Bar existiert.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$byteOffset = false;
$quietMode  = false;


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// (1.1) Optionen parsen
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   if ($arg == '-h')   help() & exit(1);                                   // Hilfe
   if ($arg == '-c') { $byteOffset=true; unset($args[$i]); continue; }     // -c: byte offset
   if ($arg == '-q') { $quietMode =true; unset($args[$i]); continue; }     // -q: quiet mode
}

// (1.2) Das verbleibende erste Argument muß ein Zeitpunkt sein.
(sizeOf($args) < 2) && help() & exit(1);
$sTime = $arg = array_shift($args);

if (strIsQuoted($sTime)) $sTime = trim(strLeft(strRight($sTime, -1), -1));
!is_datetime($sTime, array('Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s')) && echoPre('invalid argument datetime = '.$arg) & exit(1);
$datetime = strToTime($sTime.' GMT');

// (1.2) Das verbleibende zweite Argument muß ein History-File sein.
$fileName = array_shift($args);
!is_file($fileName) && echoPre('file not found "'.$fileName.'"') & exit(1);


// (2) Datei öffnen und Header auslesen
$fileSize = fileSize($fileName);
($fileSize < HistoryHeader::SIZE) && echoPre('invalid or unknown history file format: file size of "'.$fileName.'" < MinFileSize ('.HistoryHeader::SIZE.')') & exit(1);
$hFile  = fOpen($fileName, 'rb');
$header = null;
try {
   $header = new HistoryHeader(fRead($hFile, HistoryHeader::SIZE));
}
catch (MetaTraderException $ex) {
   if (strStartsWith($ex->getMessage(), 'version.unsupported'))
      echoPre('unsupported history format in "'.$fileName.'": '.$ex->getMessage()) & exit(1);
   throw $ex;
}

if ($header->getFormat() == 400) { $barSize = MT4::HISTORY_BAR_400_SIZE; $barFormat = 'Vtime/dopen/dlow/dhigh/dclose/dticks';                          }
else                      /*401*/{ $barSize = MT4::HISTORY_BAR_401_SIZE; $barFormat = 'Vtime/x4/dopen/dhigh/dlow/dclose/Vticks/x4/lspread/Vvolume/x4'; }


// (3) Anzahl der Bars bestimmen und Beginn- und Endbar auslesen
$i = 0;
$allBars = $bars = ($fileSize-HistoryHeader::SIZE)/$barSize;
if (!is_int($bars)) {
   echoPre('unexpected EOF of "'.$fileName.'"');;
   $allBars = $bars = (int) $bars;
}
$barFrom = $barTo = array();
if (!$bars) {
   $i = -1;                      // Datei enthält keine Bars
}
else {
   $barFrom = unpack($barFormat, fRead($hFile, $barSize));
   $iFrom   = 0;
   fSeek($hFile, HistoryHeader::SIZE + $barSize*($bars-1));
   $barTo   = unpack($barFormat, fRead($hFile, $barSize));
   $iTo     = $bars-1;
}


// (4) Zeitfenster von Beginn- und Endbar rekursiv bis zum gesuchten Zeitpunkt verkleinern
while ($i != -1) {
   if ($barFrom['time'] >= $datetime) {
      $i = $iFrom;
      break;
   }
   if ($barTo['time'] < $datetime) {
      $i = -1;
      break;
   }
   if ($barTo['time']==$datetime || $bars==2) {
      $i = $iTo;
      break;
   }

   $halfSize = (int) ceil($bars/2);
   $iMid     = $iFrom + $halfSize - 1;
   fSeek($hFile, HistoryHeader::SIZE + $iMid*$barSize);
   $barMid   = unpack($barFormat, fRead($hFile, $barSize));
   if ($barMid['time'] <= $datetime) { $barFrom = $barMid; $iFrom = $iMid; }
   else                              { $barTo   = $barMid; $iTo   = $iMid; }
   $bars = $iTo - $iFrom + 1;
}


// (5) Ergebnis ausgeben
if ($i>=0 && $byteOffset) $result = HistoryHeader::SIZE + $i*$barSize;
else                      $result = $i;

if      ($quietMode ) echo $result;
else if ($byteOffset) echoPre('byte offset: '.$result);
else                  echoPre('bar offset: ' .$result);

exit(0);



// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message.NL.NL);

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
Returns the offset of the first bar in a MetaTrader history file at or after a specified time or -1 if no such bar is found.

  Syntax:  $self  [OPTION]... TIME FILE

  Options:  -c  Returns the character offset of the found bar instead of the bar offset.
            -q  Quiet mode. Returns only the numeric result value.
            -h  This help screen.


END;
}
