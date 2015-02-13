<?php

require_once "vendor/autoload.php";

function getLatest(array $versions, $returnKey = FALSE) {
	$latest = NULL;
	foreach ($versions as $version) {
		$ve = explode('-', $version);
		$v = $ve[0];
		if(!isset($ve[1])) {
			$pf = 'stable';
		} else {
			$pf = $ve[1];
		}
		if($pf !== 'stable') {
			continue;
		}
		if($latest === NULL) {
			$latest = $v;
		} else {
			$comp = version_compare($latest, $v);
			if($comp === -1) {
				$latest = $v;
			}
		}
	}
	if($returnKey) {
		return array_flip($versions)[$latest];
	}
	return $latest;
}

function findVersionByTag($package, $versionName) {
	$version = new Version($versionName);
	$client = new \Packagist\Api\Client();
	foreach($client->get($package)->getVersions() as $tag => $versionClass) {
		if($versionClass->getVersionNormalized() == $version->getNormalized()) {
			return $versionClass;
		}
	}
	throw new Exception('Version not found');
}

function getRequirementsNV($name, $version) {
	if(isPlatformPackage($name)) {
		return new Chart();
	}
	$version = findVersionByTag($name, $version);
	return getRequirements($version, $name);
}

function getRequirements(\Packagist\Api\Result\Package\Version $version, $package) {
	$r = new Chart();
	$r->nodes[$package] = $version->getVersion();

	foreach ($version->getRequire() as $rName => $rVer) {
		$r->nodes[$rName] = $rVer;
		Tracy\Debugger::barDump($r);
		$r->edges->addEdge(new Edge($package, $rName));
		$reqReq = getRequirementsNV($rName, $rVer);

		$r->merge($reqReq);

		Tracy\Debugger::barDump($r);
	}

	return $r;
}

function requiredVersion($v) {
	$v = explode(' ', $v)[0];

	$v = str_replace(['<', '>', '=', '=', '~', '^', ','], "", $v);
	$v = str_replace('*', '0', $v);

	return $v;
}



class Package {
	public $name;
	public $version;
	public function __construct($name, Version $version)
	{
		$this->name = $name;
		$this->version = $version;
	}

	private $versionObject = NULL;
	public function getVersionObject() {
		if($this->versionObject === NULL) {

		}
		return $this->versionObject;
	}

	public function getPackagistVersion() {
		findVersionByTag($this->name, $this->version->version);
	}

	public function isPlatformPackage() {
		if(
			$this->name === 'php' ||
			$this->name === 'hhvm' ||
			substr($this->name, 0, 4) === 'ext-' ||
			substr($this->name, 0, 4) === 'lib-'
		) {
			return TRUE;
		}
		return FALSE;
	}

	public function getRequirements() {
		$chart = new Chart();
		$chart->nodes[$package] = $version->getVersion();

		foreach ($version->getRequire() as $rName => $rVer) {
			$r->nodes[$rName] = $rVer;
			Tracy\Debugger::barDump($r);
			$r->edges->addEdge(new Edge($package, $rName));
			$reqReq = getRequirementsNV($rName, $rVer);

			$r->merge($reqReq);

			Tracy\Debugger::barDump($r);
		}

		return $r;

	}

	public function equal(Package $package) {
		return ($package->name === $this->name);
	}
}

class Packages implements Iterator {
	protected $packages = [];

	public function addPackage(Package $package) {
		if($this->exists($package)) {
			return FALSE;
		}
		$this->packages[] = $package;
		return $this;
	}

	public function exists($package) {
		foreach ($this->packages as $key => $packageT) {
			if($packageT->equal($package)) {
				return TRUE;
			}
		}
		return FALSE;
	}

	public function merge(Packages $packages) {
		foreach($packages as $package) {
			$this->addPackage($package);
		}
		return $this;
	}

	/** Iterator */

	protected $position = 0;
	public function current() {
		return $this->packages[$this->position];
	}
	public function key () {
		return $this->position;
	}
	public function next () {
		$this->position++;
	}
	public function rewind () {
		$this->position = 0;
	}
	public function valid () {
		return isset($this->packages[$this->position]);
	}
}

class Version {
	public $version;

	public function __construct($version)
	{
		$this->version = requiredVersion($version);
	}

	public function __toString() {
		return $this->version;
	}

	public function getNormalized() {
		$compVP = new Composer\Package\Version\VersionParser();
		return $compVP->normalize($this->version);
	}
}

class Chart {
	public $nodes;
	public $edges;

	public function __construct()
	{
		$this->nodes = [];
		$this->edges = new Edges();
	}

	public function merge(Chart $toMerge) {
		$this->nodes = array_merge($toMerge->nodes, $this->nodes);
		$this->edges->merge($toMerge->edges);
		return $this;
	}
}

class Edges implements Iterator {
	protected $edges = [];

	public function addEdge(Edge $edge) {
		if($this->exists($edge)) {
			return FALSE;
		}
		$this->edges[] = $edge;
		return $this;
	}

	public function exists($from, $to = NULL) {
		if($to !== NULL) {
			$edge = new Edge($from, $to);
		} else {
			$edge = $from;
		}
		foreach ($this->edges as $key => $edgeT) {
			if($edgeT->equal($edge)) {
				return TRUE;
			}
		}
		return FALSE;
	}

	public function merge(Edges $edges) {
		foreach($edges as $edge) {
			$this->addEdge($edge);
		}
		return $this;
	}

	/** Iterator */

	protected $position = 0;
	public function current() {
		return $this->edges[$this->position];
	}
	public function key () {
		return $this->position;
	}
	public function next () {
		$this->position++;
	}
	public function rewind () {
		$this->position = 0;
	}
	public function valid () {
		return isset($this->edges[$this->position]);
	}
}

class Edge {
	public $from;
	public $to;

	public function __construct($from, $to)
	{
		$this->from = $from;
		$this->to = $to;
	}

	public function equal(Edge $e) {
		if($e->from === $this->from && $e->to === $this->to) {
			return TRUE;
		}
		return FALSE;
	}
}

