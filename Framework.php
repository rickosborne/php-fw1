<?php
namespace Org\Corfield;

/**
 * This is a helper class to work around some pass-by-reference issues
 * with __call in some versions of PHP 5.3.
 * It sucks, and needs to go away.
 * @author Rick Osborne
 */
class FW1Obj {
	private $properties = array();
	
	public function __get($property) {
		if ($this->exists($property)) {
			return $this->properties[$property];
		}
		return NULL;
	}
	public function __set($property, $value) {
		$this->properties[$property] = $value;
	}
	public function param($property, $default) {
		if (!$this->exists($property)) {
			$this->properties[$property] = $default;
		}
		return $this->properties[$property];
	}
	public function exists($property) {
		return array_key_exists($property, $this->properties);
	}
	public function delete($property) {
		if($this->exists($property)) {
			unset($this->properties[$property]);
		}
	}
	public function keyNames() {
		return array_keys($this->properties);
	}
	
}

class Framework1 {
	
	protected $context;
	protected $framework;
	protected $request;
	protected $cache;
	protected $cgiScriptFileName;
	protected $cgiScriptName;
	protected $cgiPathInfo;
	protected $appRoot;
	
	/**
	 * Object constructor, like init() in CF, but always called implicitly.
	 * @param array $settings Key/Values here will be used to overwrite defaults, like you would with CF's variables.framework 
	 */
	public function __construct(array $settings) {
		$this->cgiScriptName = self::arrayParam($_SERVER, 'SCRIPT_NAME');
		$this->cgiPathInfo = self::arrayParam($_SERVER, 'PATH_INFO');
		$this->cgiScriptFileName = self::arrayParam($_SERVER, 'SCRIPT_FILENAME');
		$this->appRoot = dirname($this->cgiScriptFileName) . '/';
		$this->basePath = dirname($this->cgiScriptName) . '/';
		$this->context   = new FW1Obj();
		$this->framework = new FW1Obj();
		$this->request   = new FW1Obj();
		$this->cache     = new FW1Obj();
		try {
			$this->setupFrameworkDefaults($settings);
			$this->onRequestStart($this->cgiScriptName);
			$this->onRequest($this->cgiScriptName);
		}
		catch(\Exception $ex) {
			$this->onError($ex);
		}
	} // ctor
	
	/**
	 * Cheap hack to allow you to access a few properties by name, like so: $fw->appRoot
	 * @param string $property The name of the property to fetch.
	 * @return mixed The value of the property (mostly strings)
	 */
	public function __get($property) {
		if ($property === 'appRoot')
			return $this->appRoot;
		elseif ($property === 'baseUrl')
			return $this->framework->baseUrl;
		elseif ($property === 'scriptName')
			return $this->cgiScriptName;
		elseif ($property === 'pathInfo')
			return $this->cgiPathInfo;
		return NULL;
	} // get
	
	/**
	 * Helper method, like cfparam, to get the values of an array or defaults.
	 * @param array $arr Haystack array
	 * @param string $key Needle name
	 * @param mixed $def Default value (empty string)
	 * @return mixed The value of the key in the array, or the given default
	 */
	protected static function arrayParam(array &$arr, $key, $def = '') {
		return array_key_exists($key, $arr) ? $arr[$key] : $def;
	} // arrayParam
	
	/**
	 * Make URLs the smart way instead of by hand.  Has some magic: if you start action with './' it knows to just append whatever you say to the base path.
	 * @param string $action The section.item form of the action, such as main.something
	 * @param string $path Base path, defaults to the base of the app
	 * @param string $queryString Additional query string parameters to append
	 * @param string $literal Force the action to use dots and not turn them into slashes.
	 * @return string The URL, suitable for printing in links
	 */
	public function buildUrl($action, $path = NULL, $queryString = '', $literal = FALSE) {
		if (is_null($path)) {
			$path = $this->framework->baseUrl;
		}
		if (is_null($queryString)) {
			$queryString = '';
		}
		$omitIndex = FALSE;
		if ($path === 'useCgiScriptName') {
			$path = $this->cgiScriptName;
			if ($this->framework->SESOmitIndex) {
				$path = dirname($path) . '/';
				$omitIndex = TRUE;
			}
		}
		if ((strpos($action, '?') !== FALSE) && ($queryString === '')) {
			$queryString = substr($action, strpos($action, '?'));
			$action = substr($action, 0, min(strpos($action, '?'), strpos($action, '#')));
		}
		if (substr($action, 0, 2) === './') {
			$literal = TRUE;
			$cosmeticAction = substr($action, 2);
		} else {
			$cosmeticAction = $this->getSectionAndItem($action);
		}
		$isHomeAction = ($cosmeticAction === $this->getSectionAndItem($this->framework->home));
		$isDefaultItem = ($this->getItem($cosmeticAction) === $this->framework->defaultItem);
		$initialDelim = '?';
		$varDelim = '&';
		$equalDelim = '=';
		$ses = FALSE;
		$anchor = '';
		$extraArgs = '';
		if (strpos($path, '?') !== FALSE) {
			if ((substr_compare($path, '?', strlen($path) - 1) === 0) || (substr_compare($path, '&', strlen($path) - 1) === 0)) {
				$initialDelim = '';
			} else {
				$initialDelim = '&';
			}
		} elseif ($this->framework->exists('generateSES') && ($this->framework->generateSES)) {
			if ($omitIndex) {
				$initialDelim = '';
			} else {
				$initialDelim = '/';
			}
			$varDelim = '/';
			$equalDelim = '/';
			$ses = TRUE;
		}
		$curDelim = $varDelim;
		$queryPart = '';
		if ($queryString !== '') {
			$qAt = strpos($queryString, '?');
			$hashAt = strpos($queryString, '#');
			$qAt = ($qAt === FALSE) ? strlen($queryString) : $qAt;
			$hashAt = ($hashAt === FALSE) ? strlen($queryString) : $hashAt;
			$extraArgs = substr($queryString, 0, min($qAt, $hashAt));
			if (strpos($queryString, '?') !== FALSE) {
				$queryPart = substr($queryString, strpos($queryString, '?'));
			}
			if (strpos($queryString, '#') !== FALSE) {
				$anchor = substr($queryString, strpos($queryString, '#'));
			}
			if ($ses) {
				$extraArgs = str_replace('=', '/', str_replace('&', '/', $extraArgs));
			}
		}
		$basePath = '';
		if ($ses) {
			if ($isHomeAction && ($extraArgs === '')) {
				$basePath = $path; 
			} elseif ($isDefaultItem && ($extraArgs === '')) {
				$basePath  = $path . $initialDelim . array_shift(explode($cosmeticAction, '.', 2));
			} elseif ($literal === TRUE) {
				$basePath  = $path . $initialDelim . $cosmeticAction;
			} else {
				$basePath  = $path . $initialDelim . str_replace('.', '/', $cosmeticAction);
			}
		} else {
			if ($isHomeAction) {
				$basePath = $path;
				$curDelim = '?';
			} else if ($isDefaultItem) {
				$basePath = $path . $initialDelim . $this->framework->action . $equalDelim . array_shift(explode($cosmeticAction, '.', 2));
			} else {
				$basePath = $path . $initialDelim . $this->framework->action . $equalDelim . $cosmeticAction;
			}
		}
		if ($extraArgs !== '') {
			$basePath .= $curDelim . $extraArgs;
			$curDelim = $varDelim;
		}
		if ($queryPart !== '') {
			if ($ses) {
				$basePath .= '?' . $queryPart;
			} else {
				$basePath .= $curDelim . $queryPart;
			}
		}
		if ($anchor !== '') {
			$basePath .= '#' . $anchor;
		}
		return $basePath;
	} // buildUrl
	
	/**
	 * Internal method to figure out which views and layouts are available and should be processed.
	 */
	protected function buildViewAndLayoutQueue() {
		$siteWideLayoutBase = $this->request->base;
		$section = $this->request->section;
		$item = $this->request->item;
		if ($this->request->exists('overrideViewAction')) {
			$section = $this->getSection($this->request->overrideViewAction);
			$item = $this->getItem($this->request->overrideViewAction);
		}
		$subsystemBase = $this->request->base;
		$this->request->view = $this->parseViewOrLayoutPath($section . '/' . $item, 'view');
		if (!$this->cachedFileExists($this->request->view)) {
			$this->request->missingView = $this->request->view;
			$this->request->delete('view');
		}
		$this->request->layouts = array();
		$testLayout = $this->parseViewOrLayoutPath($section . '/' . $item, 'layout');
		if ($this->cachedFileExists($testLayout)) {
			$layouts = $this->request->layouts;
			$layouts[] = $testLayout;
			$this->request->layouts = $layouts;
		}
		$testLayout = $this->parseViewOrLayoutPath($section, 'layout');
		if ($this->cachedFileExists($testLayout)) {
			$layouts = $this->request->layouts;
			$layouts[] = $testLayout;
			$this->request->layouts = $layouts;
		}
		if ($this->request->section !== 'default') {
			$testLayout = $this->parseViewOrLayoutPath('default', 'layout');
			if ($this->cachedFileExists($testLayout)) {
				$layouts = $this->request->layouts;
				$layouts[] = $testLayout;
				$this->request->layouts = $layouts;
			}
		}
	} // buildViewAndLayoutQueue
	
	/**
	 * Check to see if the given file exists, storing the result in a cache to make later checks faster.
	 * @param string $filePath Path to the file.
	 * @return bool TRUE if the file exists
	 */
	protected function cachedFileExists($filePath) {
		if (!$this->framework->cacheFileExists) {
			return file_exists($filePath);
		}
		$exists = $this->cache->exists('fileExists') ? $this->cache->fileExists : array();
		if (!array_key_exists($filePath, $exists)) {
			$exists[$filePath] = file_exists($filePath);
			$this->cache->fileExists = $exists;
		}
		return $exists[$filePath];
	} // cachedFileExists
	
	/**
	 * Add the given controller to the queue
	 * @param string $action The section.item formatted controller path.
	 * @throws \Exception
	 */
	public function controller($action) {
		$section = self::getSection($action);
		$item    = self::getItem($action);
		if (array_key_exists('controllerExecutionStarted', $this->request)) {
			throw new \Exception("Controller '$action' may not be added at this point.");
		}
		$tuple = array(
			'controller' => $this->getController($section),
			'key'        => $section,
			'item'       => $item
		);
		if (is_object($tuple['controller'])) {
			$this->request->param('controllers', array());
			$controllers = $this->request->controllers;
			array_push($controllers, $tuple); 
			$this->request->controllers = $controllers;
		}
	} // controller
	
	/**
	 * Override this method to return the file path for the given info and type. 
	 * @param string $pathInfo The section.item form of the path.
	 * @param string $type Values 'view' or 'layout'.
	 * @param string $fullPath Full path to the file, before alteration.
	 * @return string New path to the view or layout file to use.
	 */
	public function customizeViewOrLayoutPath($pathInfo, $type, $fullPath) {
		return $fullPath;
	} // customizeViewOrLayoutPath
	
	/**
	 * Internal method to actually perform the controller method call.
	 * @param object $obj Controller object upon which to call the method.
	 * @param string $method Name of the method to call
	 */
	protected function doController($obj, $method) {
		$reflect = new \ReflectionClass(get_class($obj));
		if($reflect->hasMethod($method) && $reflect->getMethod($method)->isPublic()) {
		// if (is_callable(array($obj, $method))) {
			$obj->{$method}($this->context);
			// call_user_func_array(array($obj, $method), $this->context);
		} else {
			// echo "Method '$method' is not available for controller '" . get_class($obj) . "'.";
		}
	} // doController
	
	/**
	 * Internal method to actually perform the service method call.  Now with argumentCollection magic!
	 * @param object $obj Service object upon which to call the method.
	 * @param string $method Name of the method to call
	 * @param array $args Arguments to pass to the method
	 * @param bool $enforceExistence Throw an exception if the method does not exist
	 * @throws \Exception
	 * @return mixed The value returned by the service call
	 */
	protected function doService($obj, $method, array $args, $enforceExistence) {
		$reflect = new \ReflectionClass(get_class($obj));
		if($reflect->hasMethod($method) && ($serv = $reflect->getMethod($method)) && $serv->isPublic()) {
		// if (is_callable(array($obj, $method))) {
			// return $obj->{$method}($args);
			// Wow, this seems like one helluva hack.  It can't be this easy, can it?
			if (empty($args)) {
				$args = array();
				$params = $serv->getParameters();
				foreach($params as $param) {
					$paramName = $param->getName();
					$args[] = $this->context->exists($paramName) ? $this->context->{$paramName} : NULL;
				}	 
			}
			return call_user_func_array(array($obj, $method), $args);
		} elseif ($enforceExistence) {
			throw new \Exception("Service method '$method' does not exist in service '" . get_class($obj) . "'.");
		}
	} // doService
	
	/**
	 * cfdump-style debugging output
	 * @param $var    The variable to output.
	 * @param $limit  Maximum recursion depth for arrays (default 0 = all)
	 * @param $label  text to display in complex data type header
	 * @param $depth  Current depth (default 0)
	 */
	public function dump(&$var, $limit = 0, $label = '', $depth = 0) {
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
		$self = $this;
		$echoFunction = function($var, $tabs, $limit = 0, $label = '', $depth = 0) use ($self) {
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
					if ($param->isOptional() && $param->isDefaultValueAvailable()) {
						$self->dump($param->getDefaultValue(), $limit, $label, $depth);
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
		};
		if (!array_key_exists('fw1dumpstarted', $_REQUEST)) {
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
		}
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
				$this->dump($aval, $limit, '', $depth);
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
			$echoFunction($var, $tabs, $limit, $label, $depth);
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
			/*
			try {
				$serial = serialize($var);
			} catch (\Exception $e) {
				$serial = 'hasclosure' . $ref->getName();
			}
			$objHash = 'o' . md5($serial);
			*/
			$objHash = spl_object_hash($var);
			$refHash = 'r' . md5($ref);
			echo "$tabs<table class=\"dump object depth${depth}\"" . (isset($seen[$refHash]) ? "" : "id=\"$refHash\"") . ">$tabs<thead>$tabs<tr><th colspan=\"2\">" . ($label != '' ? $label . ' - ' : '') . "object " . htmlentities($ref->getName()) . ($parent ? "<br/>extends " .$parent->getName() : "") . ($interfaces !== '' ? "<br/>implements " . $interfaces : "") . "</th></tr>$tabs<tbody>";
			if (isset($seen[$objHash])) {
				echo "$tabs<tr><td colspan=\"2\"><a href=\"#$refHash\">[see above for details]</a></td></tr>";
			} else {
				$seen[$objHash] = TRUE;
				$constants = $ref->getConstants();
				if (count($constants) > 0) {
					echo "$tabs<tr><td class=\"key\">CONSTANTS</td><td class=\"values\"><div>$tabs<table class=\"dump object\">";
					foreach ($constants as $constant => $cval) {
						echo "$tabs<tr><td class=\"key\">" . htmlentities($constant) . "</td><td class=\"value constant\"><div>";
						$this->dump($cval, $limit, '', $depth + 1);
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
						$this->dump($property->getValue($var), $limit, '', $depth + 1);
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
							$echoFunction($method, $tabs, $limit, '', $depth + 1);
							echo "</div></td></tr>";
						}
						echo "$tabs</table>";
					}
					echo "$tabs</div></td></tr>";
				}
			}
			echo "$tabs</tbody>$tabs</table>";
		} elseif (is_resource($var)) {
			echo "(Resource)";
		} elseif (is_numeric($var)) {
			echo  htmlentities($var);
		} elseif (is_scalar($var)) {
			echo htmlentities($var);
		} else {
			echo gettype($var);
		}
	} // dump
	
	/**
	 * Internal handler for catastrophic errors
	 * @param Exception $ex
	 */
	protected function failure(\Exception $ex) {
		echo "<h1>Error</h1>";
		if ($this->request->exists('failedAction')) {
			$fa = $this->request->failedAction;
			echo "<p>The action $fa failed.</p>";
		}
		echo "<p>" . htmlentities($ex->getMessage()) . "</p>";
		// echo $this->dump($ex);
	} // failure
	
	/**
	 * Fetch an object from the cache, or instantiate and cache it if it does not exist.
	 * @param string $type One of 'controller' or 'service'
	 * @param string $section Name of the object to fetch
	 * @return object Object, ready to use
	 */
	protected function getCachedObject($type, $section) {
		$types = $type . 's';
		$classKey = $section;
		$baseDir = $this->appRoot;
		if (!array_key_exists($classKey, $this->cache->{$types})) {
			$reqPath = $baseDir . $types . '/' . $section . '.php';
			if ($this->cachedFileExists($reqPath)) {
				require_once($reqPath);
				$classPath = '\\' . $types . '\\' . $section;
				if ($type === 'controller') {
					$obj = new $classPath($this);
				} else {
					$obj = new $classPath();
				}
				if (is_object($obj)) {
					$typeCache = $this->cache->{$types};
					$typeCache[$classKey] = $obj; 
					$this->cache->{$types} = $typeCache;
				}
			}
		}
		if (array_key_exists($classKey, $this->cache->{$types})) {
			return $this->cache->{$types}[$classKey];
		}
		return NULL;
	} // getCachedObject
	
	/**
	 * Convenience method to fetch a controller by name.
	 * @param string $section Name of the controller to fetch
	 * @return object Controller object
	 */
	protected function getController($section) {
		return $this->getCachedObject('controller', $section); 
	} // getController
	
	/**
	 * Extract the item part of a section.item action.
	 * @param string $action Action in section.item format.
	 * @return string Item name.
	 */
	protected function getItem($action) {
		return array_pop(explode('.', $this->getSectionAndItem($action), 2));
	} // getItem
	
	/**
	 * Internal magic for form preservation
	 * @return int Number for preserved form
	 */
	protected function getNextPreserveKeyAndPurgeOld() {
		$oldKeyToPurge = '';
		session_start();
		if ($this->framework->maxNumContextsPreserved > 1) {
			if (!array_key_exists('__fw1NextPreserveKey', $_SESSION)) {
				$_SESSION['__fw1NextPreserveKey'] = 1;
			}
			$nextPreserveKey = $_SESSION['__fw1NextPreserveKey'];
			$_SESSION['__fw1NextPreserveKey']++;
			$oldKeyToPurge = $nextPreserveKey - $this->framework->maxNumContextsPreserved;
		} else {
			$_SESSION['__fw1NextPreserveKey'] = '';
			$nextPreserveKey = '';
			$oldKeyToPurge = '';
		}
		if (array_key_exists($this->getPreserveKeySessionKey($oldKeyToPurge), $_SESSION)) {
			unset($_SESSION[$this->getPreserveKeySessionKey($oldKeyToPurge)]);
		}
		return $nextPreserveKey;
	} // getNextPreserveKeyAndPurgeOld
	
	/**
	 * Abstraction magic for form preservation
	 * @param string $preserveKey Number of preserved form
	 * @return string Token to use for preserved form data.
	 */
	protected function getPreserveKeySessionKey($preserveKey) {
		return "__fw" . $preserveKey;
	} // getPreserveKeySessionKey
	
	/**
	 * Extract the section part of a section.item action
	 * @param string $action Action in section.item format.
	 * @return string Section name
	 */
	protected function getSection($action) {
		return array_shift(explode('.', $this->getSectionAndItem($action), 2));
	} // getSection
	
	/**
	 * Expad, contract, or extract a section.item token from the given action
	 * @param string $action
	 * @return string Action in section.item format
	 */
	public function getSectionAndItem($action = '') {
		if (strlen($action) === 0) {
			return $this->framework->home;
		}
		$parts = explode('.', $action);
		if (count($parts) === 1) {
			return $this->framework->defaultSection . '.' . $action; 
		} else if (strlen($parts[0]) === 0) {
			return $this->framework->defaultSection . $action;
		}
		return $parts[0] . '.' . $parts[1];
	} // getSectionAndItem
	
	/**
	 * Convenience function to get a service by name
	 * @param string $section Name of the service
	 * @return object Service object
	 */
	protected function getService($section) {
		return $this->getCachedObject('service', $section);
	} // getService
	
	/**
	 * Override this function to use a different request context key name for service results.
	 * @param string $action Service action in section.item format
	 * @return string Request context key to use to store service results
	 */
	public function getServiceKey($action) {
		return 'data';
	} // getServiceKey

	/**
	 * Internal implementation to actually include the layout content.
	 * @param string $layoutPath File path to the layout
	 * @param string $body Content to include as the body
	 * @throws \Exception
	 * @return string Content generated by the layout
	 */
	protected function internalLayout($layoutPath, $body) {
		$rc = $this->context;
		$fw = $this;
		if (!$this->request->exists('controllerExecutionComplete')) {
			throw new \Exception('Invalid to call the layout method at this point.');
		}
		ob_start();
		require "$layoutPath";
		return ob_get_clean();
	} // internalLayout
	
	/**
	 * Internal implementation to actually include the view content.
	 * @param string $viewPath File path to the view
	 * @param string $body Content to include as the body
	 * @throws \Exception
	 * @return string Content generated by the view
	 */
	protected function internalView($viewPath, $args = array()) {
		$rc = $this->context;
		$fw = $this;
		if (!$this->request->exists('controllerExecutionComplete')) {
			throw new \Exception('Invalid to call the view method at this point.');
		}
		ob_start();
		require "$viewPath";
		return ob_get_clean();
	} // internalView

	/**
	 * Return the content generated by the given layout
	 * @param string $path Layout action in section.item format
	 * @param string $body Content to include as the body
	 * @return string Content generated by the layout
	 */
	public function layout($path, $body) {
		return $this->internalLayout($this->parseViewOrLayoutPath($path, 'layout'), $body);
	} // layout
	
	/**
	 * Override this method to perform your own error handling
	 * @param Exception $ex
	 */
	public function onError(\Exception $ex) {
		$this->failure($ex);
	} // onError
	
	/**
	 * Override this method to handle missing views on your own
	 * @param object $rc Request context
	 */
	public function onMissingView($rc) {
		$this->viewNotFound();
	} // onMissingView
	
	/**
	 * Handle the request.  Do not override this method!
	 * @param string $targetPath Path information for the request
	 */
	public function onRequest($targetPath) {
		$once = array();
		$this->request->controllerExecutionStarted = TRUE;
		if ($this->request->exists('controllers')) {
			foreach ($this->request->controllers as $tuple) {
				if (!array_key_exists($tuple['key'], $once)) {
					$once[$tuple['key']] = 0;
					$this->doController($tuple['controller'], 'before');
				}
				$this->doController($tuple['controller'], 'start' . $tuple['item']);
				$this->doController($tuple['controller'], $tuple['item']);
				$once[$tuple['key']]++;
			}
		}
		foreach ($this->request->services as $tuple) {
			$result = $this->doService($tuple['service'], $tuple['item'], $tuple['args'], $tuple['enforceExistence']);
			if ($tuple['key'] !== '') {
				$this->context->{$tuple['key']} = $result;
			}
		}
		$this->request->serviceExecutionComplete = TRUE;
		if ($this->request->exists('controllers')) {
			foreach ($this->request->controllers as $tuple) {
				$this->doController($tuple['controller'], 'end' . $tuple['item']);
				if (!array_key_exists($tuple['key'], $once)) {
					$once[$tuple['key']] = -1;
				}
				$once[$tuple['key']]--;
				if ($once[$tuple['key']] === 0) {
					$this->doController($tuple['controller'], 'after');
				}
			}
		}
		$this->request->controllerExecutionComplete = TRUE;
		$this->buildViewAndLayoutQueue();
		if ($this->request->exists('view')) {
			$out = $this->internalView($this->request->view);
		} else {
			$out = $this->onMissingView($this->context);
		}
		foreach ($this->request->layouts as $layout) {
			if ($this->request->exists('layout') && !$this->request->layout) {
				break;
			}
			$out = $this->internalLayout($layout, $out);
		}
		echo $out;
	} // onRequest
	
	/**
	 * Framework setup tasks for the request that is about to happen.  Do not override this method!
	 * @param string $targetPath Path information for the request
	 */
	public function onRequestStart($targetPath) {
		$pathInfo = $this->cgiPathInfo;
		$sesIx = 0;
		$sesN = 0;
		$this->setupRequestDefaults();
		$this->setupApplicationWrapper();
		if ((strlen($pathInfo) > strlen($this->cgiScriptName)) && (substr($pathInfo, 0, strlen($this->cgiScriptName)) === $this->cgiScriptName)) {
			// path contains the script
			$pathInfo = substr($pathInfo, strlen($this->cgiScriptName));
		} else if ((strlen($pathInfo) > 0) && ($pathInfo === substr($this->cgiScriptName, 0, strlen($pathInfo)))) {
			// path is the same as the script
			$pathInfo = '';
		}
		if (substr($pathInfo, 0, 1) === '/') {
			$pathInfo = substr($pathInfo, 1);
		}
		$pathInfo = explode('/', $pathInfo);
		$sesN =count($pathInfo);
		for ($sesIx = 0; $sesIx < $sesN; $sesIx++) {
			if ($sesIx === 0) {
				$this->context->{$this->framework->action} = $pathInfo[$sesIx];
			} else if ($sesIx === 1) {
				$this->context->{$this->framework->action} = $pathInfo[$sesIx-1] . '.' . $pathInfo[$sesIx];
			} else if (($sesIx % 2) === 0) {
				$this->context->{$pathInfo[$sesIx]} = '';
			} else {
				$this->context->{$pathInfo[$sesIx-1]} = $pathInfo[$sesIx];
			}
		} // for each ses index
		foreach ($_REQUEST as $key => $val) {
			$this->context->{$key} = $val;
		}
		if (!$this->context->exists($this->framework->action)) {
			$this->context->{$this->framework->action} = $this->framework->home;
		} else {
			$this->context->{$this->framework->action} = $this->getSectionAndItem($this->context->{$this->framework->action});
		}
		$this->request->{$this->framework->action} = self::validateAction(strtolower($this->context->{$this->framework->action}));
		$this->setupRequestWrapper(TRUE);
	} // onRequestStart
	
	/**
	 * Internal shim to hand off view/layout logic
	 * @param string $path Action in section.item format.
	 * @param string $type Either 'view' or 'layout'
	 * @return string Path to the view/layout to use
	 */
	protected function parseViewOrLayoutPath($path, $type) {
		$pathInfo = array(
			'path' => $path,
			'base' => $this->request->base,
		);
		return $this->customizeViewOrLayoutPath($pathInfo, $type, "${pathInfo['base']}${type}s/${pathInfo['path']}.php");
	} // parseViewOrLayoutPath
	
	/**
	 * Redirect the user by sending the appropriate Location header
	 * @param string $action Target action in section.item format
	 * @param string $preserve
	 * @param string $append Comma-delimited list of key names to append from the request context (or 'all')
	 * @param string $path 
	 * @param string $queryString Additional query string parameters to append
	 */
	public function redirect($action, $preserve = 'none', $append = 'none', $path = NULL, $queryString = '') {
		$baseQueryString = array();
		$key = '';
		$val = '';
		$keys = '';
		$preserveKey = '';
		$targetUrl = '';
		if ($append !== 'none') {
			if ($append === 'all') {
				$keys = array_keys($this->context);
			} else {
				$keys = explode(',', $append);
			}
			foreach ($keys as $key) {
				if (array_key_exists($key, $this->context) && is_scalar($this->context[$key])) {
					$baseQueryString[] = $key . '=' . urlencode($this->context[$key]);
				}
			}
		}
		$baseQueryString = implode('&', $baseQueryString);
		if ($baseQueryString !== '') {
			if ($queryString !== '') {
				if ((substr_compare($queryString, '?', 0) === 0) || (substr_compare($queryString, '#', 0) === 0)) {
					$baseQueryString .= $queryString;
				} else {
					$baseQueryString .= '&' . $queryString;
				}
			}
		} else {
			$baseQueryString = $queryString;
		}
		$targetUrl = $this->buildUrl($action, $path, $baseQueryString);
		if ($preserveKey !== '') {
			if (strpos($targetUrl, '?') !== FALSE) {
				$preserveKey = '&' . $this->framework->preserveKeyURLKey . '=' . $preserveKey;
			} else {
				$preserveKey = '?' . $this->framework->preserveKeyURLKey . '=' . $preserveKey;
			}
			$targetUrl .= $preserveKey;
		}
		header('Location: ' . $targetUrl);
		exit;
	} // redirect
	
	/**
	 * Add the given service to the queue to be handled later
	 * @param string $action Name of the service
	 * @param string $key Name of the key in the request context under which the result will be stored
	 * @param array $args Arguments to pass to the service. (Will try its best to emulate argumentCollection if empty.)
	 * @param bool $enforceExistence Throw an error of the service method does not exist
	 * @throws \Exception
	 */
	public function service($action, $key, array $args = array(), $enforceExistence = TRUE) {
		$section = $this->getSection($action);
		$item = $this->getItem($action);
		if ($this->request->exists('serviceExecutionComplete')) {
			throw new \Exception("Service '$action' may not be added at this point.");
		}
		$tuple = array(
			'service' => $this->getService($section),
			'item'    => $item,
			'key'     => $key,
			'args'    => $args,
			'enforceExistence' => $enforceExistence
		);
		if (is_object($tuple['service'])) {
			$services = $this->request->services;
			array_push($services, $tuple); 
			$this->request->services = $services;
		} else if ($enforceExistence) {
			throw new \Exception("Service '$action' does not exist");
		}
	} // service
	
	/**
	 * Override this function with a hook to be called as the framework is loading
	 */
	public function setupApplication() {}
	
	/**
	 * Create the caches and other variables needed by the framework
	 */
	protected function setupApplicationWrapper() {
		$this->cache->lastReload = time();
		$this->cache->fileExists = array();
		$this->cache->controllers = array();
		$this->cache->services = array();
		$this->setupApplication();
	} // setupApplicationWrapper
	
	/**
	 * Initialize the framework with the default settings, overriding them with any user-specified settings as required
	 * @param array $settings
	 */
	protected function setupFrameworkDefaults(array $settings) {
		$defaults = array(
			'action'         => 'action',
			'defaultSection' => 'main',
			'defaultItem'    => 'home',
			'generateSES'    => FALSE,
			'SESOmitIndex'   => FALSE,
			'base'           => $this->appRoot,
			'baseUrl'        => 'useCgiScriptName',
			'cacheFileExists' => TRUE,
			'suppressImplicitService' => FALSE,
			'maxNumContextsPreserved' => 10,
			'preserveKeyURLKey' => 'fwpk'
		);
		foreach ($defaults as $key => $val) {
			$this->framework->param($key, array_key_exists($key, $settings) ? $settings[$key] : $val);
		}
		$this->framework->param('home', $this->framework->defaultSection . '.' . $this->framework->defaultItem);
	} // setupFrameworkDefaults
	
	/**
	 * Override this method to have a hook into when the framework is just about to process the request
	 */
	public function setupRequest() {}
	
	/**
	 * Set up request-specific framework variables
	 * (Yes this is kindof silly in PHP.)
	 */
	protected function setupRequestDefaults() {
		$this->request->base = $this->framework->base;
	} // setupRequestDefaults
	
	/**
	 * Populate the variables needed for this specific request
	 * @param bool $runSetup
	 */
	protected function setupRequestWrapper($runSetup = FALSE) {
		$this->request->section  = $this->getSection($this->request->action);
		$this->request->item     = $this->getItem($this->request->action);
		$this->request->services = array();
		if ($runSetup) {
			$this->setupRequest();
		}
		$this->controller($this->request->action);
		if (!$this->framework->suppressImplicitService) {
			$this->service($this->request->action, $this->getServiceKey($this->request->action), array(), FALSE);
		}
	} // setupRequestWrapper
	
	/**
	 * Force the specified view instead of the default view 
	 * @param string $action View action in setion.item format. 
	 */
	public function setView($action) {
		$this->request->overrideViewAction = $this->validateAction($action);
	} // setView
	
	/**
	 * Ensure the user isn't trying to get us to include files outside of our scope.
	 * @param string $action Action in section.item format.
	 * @throws \Exception
	 * @return string Clean action.
	 */
	protected static function validateAction($action) {
		if ((strpos($action, '/') !== FALSE) || (strpos($action, '\\') !== FALSE)) {
			throw new \Exception('Actions cannot contain slashes');
		}
		return $action;
	} // validateAction
	
	/**
	 * Process the given view
	 * @param string $path View action in section.item format
	 * @param string $args Arguments to pass to the view
	 * @return string Content generated by the view
	 */
	public function view($path, $args = NULL) {
		return $this->internalView($this->parseViewOrLayoutPath($path, 'view'), $args);
	} // view
	
	/**
	 * Stub handler for panic situations when we can't find the right view to use
	 * @throws \Exception
	 */
	protected function viewNotFound() {
		throw new \Exception("Unable to find a view for '" . $this->request->action . "' action.");
	} // viewNotFound

}