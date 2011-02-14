<?php 
namespace org\rickosborne\php;

class CFDump {

	public function __construct() {
		foreach (func_get_args() as $arg)
			self::dump($arg);
	} // ctor
	
	/**
	 * cfdump-style debugging output
	 * @param $var    The variable to output.
	 * @param $limit  Maximum recursion depth for arrays (default 0 = all)
	 * @param $label  text to display in complex data type header
	 * @param $depth  Current depth (default 0)
	 */
	public static function dump(&$var, $limit = 0, $label = '', $depth = 0) {
		if (!is_int($depth))
			$depth = 0;
		if (!is_int($limit))
			$limit = 0;
		if (($limit > 0) && ($depth >= $limit))
			return;
		static $seen = array();
		$he = function ($s) { return htmlentities($s); };
		$tabs = "\n" . str_repeat("\t", $depth);
		$depth++;
		$printCount = 0;
		self::dumpCssJs();
		if (is_array($var)) {
			// It turns out that identity (===) in PHP isn't actually identity.  It's more like "do you look similar enough to fool an untrained observer?".  Lame!
			// $label = $label === '' ? (($var === $_POST) ? '$_POST' : (($var === $_GET) ? '$_GET' : (($var === $_COOKIE) ? '$_COOKIE' : (($var === $_ENV) ? '$_ENV' : (($var === $_FILES) ? '$_FILES' : (($var === $_REQUEST) ? '$_REQUEST' : (($var === $_SERVER) ? '$_SERVER' : (isset($_SESSION) && ($var === $_SESSION) ? '$_SESSION' : '')))))))) : $label;      
			$c = count($var);
			if(isset($var['fw1recursionsentinel'])) {
				echo "(Recursion)";
			}
			$aclass = (($c > 0) && array_key_exists(0, $var) && array_key_exists($c - 1, $var)) ? 'array' : 'struct';
			$var['fw1recursionsentinel'] = true;
			echo "$tabs<table class=\"dump ${aclass} depth${depth}\">$tabs<thead><tr><th colspan=\"2\">" . ($label != '' ? $label . ' - ' : '') . "array" . ($c > 0 ? "" : " [empty]") . "</th></tr></thead>$tabs<tbody>";
			foreach ($var as $index => $aval) {
				if ($index === 'fw1recursionsentinel')
					continue;
				echo "$tabs<tr><td class=\"key\">" . $he($index) . "</td><td class=\"value\"><div>";
				self::dump($aval, $limit, '', $depth);
				echo "</div></td></tr>";
				$printCount++;
				if (($limit > 0) && ($printCount >= $limit) && ($aclass === 'array'))
					break;
			}
			echo "$tabs</tbody>$tabs</table>";
			// unset($var['fw1recursionsentinel']);
		} elseif (is_string($var)) {
			echo $var === '' ? '[EMPTY STRING]' : htmlentities($var);
		} elseif (is_bool($var)) {
			echo $var ? "TRUE" : "FALSE";
		} elseif (is_callable($var) || (is_object($var) && is_subclass_of($var, 'ReflectionFunctionAbstract'))) {
			self::dumpFunction($var, $tabs, $limit, $label, $depth);
		} elseif (is_resource($var)) {
			echo "(Resource)";
		} elseif (is_float($var)) {
			echo "(float) " . htmlentities($var);
		} elseif (is_int($var)) {
			echo "(int) " . htmlentities($var);
		} elseif (is_null($var)) {
			echo "NULL";
		} elseif (is_object($var)) {
			$ref = new \ReflectionObject($var);
			$parent = $ref->getParentClass();
			$interfaces = implode("<br/>implements ", $ref->getInterfaceNames());
			$objHash = spl_object_hash($var);
			$refHash = 'r' . md5($ref);
			$objClassName = $ref->getName();
			if ($objClassName === 'SimpleXMLElement') {
				echo "$tabs<table class=\"dump xml depth${depth}\">$tabs<thead>$tabs<tr><th colspan=\"2\">" . ($label != '' ? $label . ' - ' : '') . "xml element</th></tr>$tabs<tbody>";
				self::trKeyValue('Name', $var->getName());
				// echo "<tr><td class=\"key\">Name</td><td class=\"value\">" . htmlentities($var->getName()) . "</td></tr>\n";
				$attribs = array();
				foreach ($var->attributes() as $attribName => $attribValue) {
					$attribs[$attribName] = $attribValue;
				}
				if (count($attribs) > 0) {
					echo "$tabs<tr><td class=\"key\">Attributes</td><td class=\"values\"><div>$tabs<table class=\"dump xml attributes\"><tbody>";
					foreach ($attribs as $attribName => $attribValue) {
						self::trKeyValue($attribName, $attribValue);
					}
					echo "$tabs</tbody></table>$tabs</div></td></tr>";
				}
				$xmlText = trim((string) $var);
				if ($xmlText !== '') {
					self::trKeyValue('Text', $xmlText);
				}
				if ($var->count() > 0) {
					echo "$tabs<tr><td class=\"key\">Children</td><td class=\"values\"><div>$tabs<table class=\"dump xml children\"><tbody>";
					$childNum = 0;
					foreach ($var->children() as $child) {
						echo "$tabs<tr><td class=\"key\">" . $childNum . "</td><td class=\"value child\"><div>";
						self::dump($child, $limit, '', $depth + 1);
						echo "</div></td></tr>";
						$childNum++;
					}
					echo "$tabs</tbody></table>$tabs</div></td></tr>";
				}
			} else {
				echo "$tabs<table class=\"dump object depth${depth}\"" . (isset($seen[$refHash]) ? "" : "id=\"$refHash\"") . ">$tabs<thead>$tabs<tr><th colspan=\"2\">" . ($label != '' ? $label . ' - ' : '') . "object " . htmlentities($objClassName) . ($parent ? "<br/>extends " .$parent->getName() : "") . ($interfaces !== '' ? "<br/>implements " . $interfaces : "") . "</th></tr>$tabs<tbody>";
				if (isset($seen[$objHash])) {
					echo "$tabs<tr><td colspan=\"2\"><a href=\"#$refHash\">[see above for details]</a></td></tr>";
				} else {
					$seen[$objHash] = TRUE;
					$constants = $ref->getConstants();
					if (count($constants) > 0) {
						echo "$tabs<tr><td class=\"key\">CONSTANTS</td><td class=\"values\"><div>$tabs<table class=\"dump object\">";
						foreach ($constants as $constant => $cval) {
							echo "$tabs<tr><td class=\"key\">" . htmlentities($constant) . "</td><td class=\"value constant\"><div>";
							self::dump($cval, $limit, '', $depth + 1);
							echo "</div></td></tr>";
						}
						echo "$tabs</table>$tabs</div></td></tr>";
					}
					$properties = $ref->getProperties();
					if (count($properties) > 0) {
						echo "$tabs<tr><td class=\"key\">PROPERTIES</td><td class=\"values\"><div>$tabs<table class=\"dump object\">";
						foreach ($properties as $property) {
							echo "$tabs<tr><td class=\"key\">" . htmlentities(implode(' ', \Reflection::getModifierNames($property->getModifiers()))) . " " . $he($property->getName()) . "</td><td class=\"value property\"><div>";
							$wasHidden = $property->isPrivate() || $property->isProtected();
							$property->setAccessible(TRUE);
							$propVal = $property->getValue($var);
							self::dump($propVal, $limit, '', $depth + 1);
							if ($wasHidden) { $property->setAccessible(FALSE); }
							echo "</div></td></tr>";
						}
						echo "$tabs</table>$tabs</div></td></tr>";
					}
					$methods = $ref->getMethods();
					if (count($methods) > 0) {
						echo "$tabs<tr><td class=\"key\">METHODS</td><td class=\"values shh\"><div>";
						if (isset($seen[$refHash])) {
							echo "<a href=\"#$refHash\">[see above for details]</a>";
						} else {
							$seen[$refHash] = TRUE;
							echo "$tabs<table class=\"dump object\">";
							foreach ($methods as $method) {
								echo "$tabs<tr><td class=\"key\">" . htmlentities($method->getName()) . "</td><td class=\"value function\"><div>";
								self::dumpFunction($method, $tabs, $limit, '', $depth + 1);
								echo "</div></td></tr>";
							}
							echo "$tabs</table>";
						}
						echo "$tabs</div></td></tr>";
					}
				}
			}
			echo "$tabs</tbody>$tabs</table>";
		} elseif (is_numeric($var)) {
			echo  htmlentities($var);
		} elseif (is_scalar($var)) {
			echo htmlentities($var);
		} else {
			echo gettype($var);
		}
	} // dump

	private static function dumpFunction($var, $tabs, $limit = 0, $label = '', $depth = 0) {
		if (!is_subclass_of($var, 'ReflectionFunctionAbstract')) {
			$var = new \ReflectionFunction($var);
		}
		echo "$tabs<table class=\"dump function depth${depth}\">$tabs<thead><tr><th>" . ($label != '' ? $label . ' - ' : '') . (is_callable(array($var, 'getModifiers')) ? htmlentities(implode(' ', \Reflection::getModifierNames($var->getModifiers()))) : '') . " function " . htmlentities($var->getName()) . "</th></tr></thead>$tabs<tbody>";
		echo "$tabs<tr><td class=\"value\">$tabs<table class=\"dump layout\">$tabs<tr><th>Parameters:</th><td>";
		$params = $var->getParameters();
		if (count($params) > 0) {
			echo "</td></tr>$tabs<tr><td colspan=\"2\">$tabs<table class=\"dump param\">$tabs<thead><tr><th>Name</th><th>Array/Ref</th><th>Required</th><th>Default</th></tr></thead>$tabs<tbody>";
			foreach ($params as $param) {
				echo "$tabs<tr><td>" . htmlentities($param->getName()) . "</td><td>" . ($param->isArray() ? "Array " : "") . ($param->isPassedByReference() ? "Reference" : "") . "</td><td>" . ($param->isOptional() ? "Optional" : "Required") . "</td><td>";
				if ($param->isOptional()) {
					$default = $param->getDefaultValue();
					self::dump($default, $limit, $label, $depth);
				}
				echo "</td></tr>";
			}
			echo "$tabs</tbody>$tabs</table>";
		} else {
			echo "none</td></tr>";
		}
		$comment = trim($var->getDocComment());
		if (($comment !== NULL) && ($comment !== '')) {
			echo "$tabs<tr><th>Doc Comment:</th><td><kbd>" . str_replace("\n", "<br/>", htmlentities($comment)) . "</kbd></td></tr>";
		}
		echo "</table>$tabs</td></tr>";
		echo "$tabs</tbody>$tabs</table>";
	} // dumpFunction
	
	private static function trKeyValue($key, $value, $keyClass = '', $valueClass = '') {
		echo "<tr><td class=\"key ${keyClass}\">" . htmlentities($key) . "</td><td class=\"value ${valueClass}\">" . htmlentities($value) . "</td></tr>\n";
	} // trKeyValue
	
	private static function dumpCssJs() {
		if (array_key_exists('fw1dumpstarted', $_REQUEST)) { return; }
		$_REQUEST['fw1dumpstarted'] = TRUE;
		echo<<<DUMPCSSJS
<style type="text/css">/* fw/1 dump */
table.dump { color: black; background-color: white; font-size: xx-small; font-family: verdana,arial,helvetica,sans-serif; border-spacing: 0; border-collapse: collapse; }
table.dump th { text-indent: -2em; padding: 0.25em 0.25em 0.25em 2.25em; color: #fff; }
table.dump td { padding: 0.25em; }
table.dump .key { cursor: pointer; }
table.dump td.shh { background-color: #ddd; }
table.dump td.shh div { display: none; }
table.dump td.shh:before { content: "..."; }
table.dump th, table.dump td { border-width: 2px; border-style: solid; border-spacing: 0; vertical-align: top; text-align: left; }
table.dump.object, table.dump.object > * > tr > td, table.dump.object > thead > tr > th { border-color: #f00; }
table.dump.object > thead > tr > th { background-color: #f44; }
table.dump.object > tbody > tr > .key { background-color: #fcc; }
table.dump.array, table.dump.array > * > tr > td, table.dump.array > thead > tr > th { border-color: #060; }
table.dump.array > thead > tr > th { background-color: #090; }
table.dump.array > tbody > tr > .key { background-color: #cfc; }
table.dump.struct, table.dump.struct > * > tr > td, table.dump.struct > thead > tr > th { border-color: #00c; }
table.dump.struct > thead > tr > th { background-color: #44c; }
table.dump.struct > tbody > tr > .key { background-color: #cdf; }
table.dump.xml, table.dump.xml > * > tr > td, table.dump.xml > thead > tr > th { border-color: #888; }
table.dump.xml > thead > tr > th { background-color: #aaa; }
table.dump.xml > tbody > tr > .key { background-color: #ddd; }
table.dump.function, table.dump.function > * > tr > td, table.dump.function > thead > tr > th { border-color: #a40; }
table.dump.function > thead > tr > th { background-color: #c60; }
table.dump.layout, table.dump.layout > * > tr > td, table.dump.layout > thead > tr > th { border-color: #fff; }
table.dump.layout > * > tr > th { font-style: italic; background-color: #fff; color: #000; font-weight: normal; border: none; }
table.dump.param, table.dump.param > * > tr > td, table.dump.param > thead > tr > th { border-color: #ddd; }
table.dump.param > thead > tr > th  { background-color: #eee; color: black; font-weight: bold; }
</style>
<script type="text/javascript" language="JavaScript">
(function(w,d){
	var addEvent = function(o,t,f) {
		if (o.addEventListener) o.addEventListener(t,f,false);
		else if (o.attachEvent) {
			o['e' + t + f] = f;
			o[t + f] = function() { o['e' + t + f](w.event); }
			o.attachEvent('on' + t, o[t + f]);
		}
	}; // addEvent
	var clickCell = function(e) {
		var target = e.target || this;
		var sib = target.nextSibling;
		if (sib && sib.tagName && (sib.tagName.toLowerCase() === 'td')) {
			if (/(^|\s)shh(\s|$)/.test(sib.className)) sib.className = sib.className.replace(/(^|\s)shh(\s|$)/, ' ');
			else sib.className += ' shh';
		}
		if (e && e.stopPropagation) e.stopPropagation();
		else w.event.cancelBubble = true;
		return false;
	}; // clickCell
	var collapsifyDumps = function() {
		setTimeout(function() {
			var tables = document.getElementsByTagName('table');
			for(var t = 0; t < tables.length; t++) {
				var table = tables[t];
				var dumpPattern = /(^|\s)dump(\s|$)/;
				var depthPattern = /(^|\s)depth1(\s|$)/;
				if (! (dumpPattern.test(table.className) && depthPattern.test(table.className) ))
					continue;
				var cells = table.getElementsByTagName('td');
				var keyPattern = /(^|\s)key(\s|$)/;
				var keyCount = 0;
				for (var c = 0; c < cells.length; c++) {
					var cell = cells[c];
					if (! (keyPattern.test(cell.className)))
						continue;
					addEvent(cell, 'click', clickCell);
				} // for k
			} // for t
		}, 250);
	}; // collapsify dumps
	if (d.addEventListener) d.addEventListener("DOMContentLoaded", collapsifyDumps, false);
	else d.onreadystatechange = function() { if (d.readyState === 'interactive') collapsifyDumps(this); };
})(window,document);
</script>
DUMPCSSJS;
	} // dumpCssJs

} // Class