<?php
require_once "vendor/autoload.php";

use Guzzle\Http\Client as GuzzleClient;
use Doctrine\Common\Cache\FilesystemCache;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\DefaultCacheStorage;

function ApiClientCreator() {
	$httpClient = new GuzzleClient();
	$cachePlugin = new CachePlugin(array(
		'storage' => new DefaultCacheStorage(
			new DoctrineCacheAdapter(
				new FilesystemCache(__DIR__ . '/cache')
			)
		)
	));
	$httpClient->addSubscriber($cachePlugin);
	return new \Packagist\Api\Client($httpClient);
}

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
	$client = ApiClientCreator();
	foreach($client->get($package)->getVersions() as $tag => $versionClass) {
		if($versionClass->getVersionNormalized() == $version->getNormalized()) {
			return $versionClass;
		}
	}
	throw new Exception('Version not found');
}

function getRequirementsNV($name, $version) {
	$package = new Package($name, new Version($version));
	if($package->isPlatformPackage()) {
		return new Chart();
	}
	return $package->getRequirements();
}
/*
function getRequirements(\Packagist\Api\Result\Package\Version $version, $package) {
	$r = new Chart();
	$r->nodes[$package] = $version->getVersion();

	foreach ($version->getRequire() as $rName => $rVer) {
		$r->nodes[$rName] = $rVer;
		$r->edges->addEdge(new Edge($package, $rName));
		$reqReq = getRequirementsNV($rName, $rVer);

		$r->merge($reqReq);
	}

	return $r;
}
*/
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
			throw new Exception;
		}
		return $this->versionObject;
	}


	public function getPackagistVersion() {
		return findVersionByTag($this->name, $this->version->version);
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
		$version = $this->getPackagistVersion();
		$chart = new Chart();
		$chart->nodes->addPackage($this);

		foreach ($version->getRequire() as $rName => $rVer) {
			$packageO = new Package($rName, new Version($rVer));
			$chart->nodes->addPackage($packageO);
			$chart->edges->addEdge(new Edge($this, $packageO));
			$reqReq = getRequirementsNV($rName, $rVer);

			$chart->merge($reqReq);
		}

		return $chart;

	}

	public function equal(Package $package) {
		return ($package->name === $this->name);
	}


	public function __toString() {
		return $this->name;
	}
}

class Packages implements Iterator {
	/** @var Package[] */
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
	/** @var Packages */
	public $nodes;
	/** @var  Edges */
	public $edges;

	public function __construct()
	{
		$this->nodes = new Packages();
		$this->edges = new Edges();
	}

	public function merge(Chart $toMerge) {
		$this->nodes->merge($toMerge->nodes);
		$this->edges->merge($toMerge->edges);
		return $this;
	}
}

class Edges implements Iterator {
	/** @var Edge[] */
	protected $edges = [];

	public function addEdge(Edge $edge) {
		if($this->exists($edge)) {
			return FALSE;
		}
		$this->edges[] = $edge;
		return $this;
	}

	public function exists($edge) {
		foreach ($this->edges as $edgeT) {
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

	/**
	 * @return Edge
	 */
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
	/** @var Package  */
	public $from;
	/** @var  Package */
	public $to;

	public function __construct(Package $from, Package $to)
	{
		$this->from = $from;
		$this->to = $to;
	}

	public function equal(Edge $e) {
		if($e->from->equal($this->from) && $e->to->equal($this->to)) {
			return TRUE;
		}
		return FALSE;
	}
}

