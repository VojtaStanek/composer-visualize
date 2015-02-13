<?php
require_once "vendor/autoload.php";
\Tracy\Debugger::enable();


require_once "lib.php";


$client = ApiClientCreator();
$package = "nette/nette";
$pkg = $client->get($package);

$vers = [];
foreach($pkg->getVersions() as $verName => $version) {
	$vers[$verName] = $version->getVersionNormalized();
}
$versionKey = getLatest($vers, TRUE);
echo "Package $package, version $versionKey" . PHP_EOL;

/** @var $version \Packagist\Api\Result\Package\Version */
$version = $pkg->getVersions()[$versionKey];

$packageO = new Package($package, new Version($versionKey));

$chart = $packageO->getRequirements();



// var_dump($chart);

?>

<!doctype html>
<html>
<head>
	<title>Composer visualization</title>

	<script type="text/javascript" src="bower_components/vis/dist/vis.js"></script>
	<link href="bower_components/vis/dist/vis.css" rel="stylesheet" type="text/css" />
	<style>
		body {
			margin: 0px;
		}

		#mynetwork {
			top: 0;
			l
		}

	</style>
</head>

<body>

<div id="mynetwork"></div>

<script type="text/javascript">
	// create an array with nodes
	var nodes = [
		<?php
			$show = '';
			foreach($chart->nodes as $package) {
				$show .= '{id: "' . $package . '", label: "' . $package . '"},';
			}
			echo $show;
		?>
	];

	// create an array with edges
	var edges = [
		<?php
			$show = '';
			foreach($chart->edges as $id => $edge) {
				$show .= '{id: ' . $id . ', from: "' . $edge->from. '", to: "' . $edge->to . '"},';
			}
			echo $show;
		?>
	];

	// create a network
	var container = document.getElementById('mynetwork');
	var data= {
		nodes: nodes,
		edges: edges,
	};
	var options = {
		width: '100vw',
		height: '100vh',
		physics: {
			barnesHut: {
				gravitationalConstant: -80000,
				centralGravity: 0,
				springLength: 70,
				springConstant: 0.0144,
				damping: 0.1
			}
		},
		smoothCurves: false
	};
	var network = new vis.Network(container, data, options);
</script>

</body>
</html>
