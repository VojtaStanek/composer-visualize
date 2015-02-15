<?php
require_once "vendor/autoload.php";
\Tracy\Debugger::enable();
Tracy\Debugger::$strictMode = TRUE;


require_once "lib.php";


$client = ApiClientCreator();

$packagesRequest = isset($_GET['package']) ? $_GET['package'] : "nette/nette";

$result = new Chart();
foreach(explode(',', $packagesRequest) as $package) {
	$pkg = $client->get($package);
	$vers = [];

	foreach($pkg->getVersions() as $verName => $version) {
		$vers[$verName] = $version->getVersionNormalized();
	}
	$versionKey = getLatest($vers, TRUE);

	$packageO = new Package($package, new Version($versionKey));

	$result->merge($packageO->getRequirements());
}

$chart = $result;


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
			background-color: #000000;
			color: white;
		}

		#mynetwork {
			top: 0;
			position: absolute;
			z-index: -10;
		}

		#overflow {

		}

		#overflow > * {
			float: left;
			display: inline-block;
			clear: both;
		}

		* {
			outline: none;
		}

	</style>
</head>

<body>

<div id="mynetwork"></div>
<div id="overflow">
	<h1>Composer visualizer</h1>
	<form method="get" action="index.php">
		<input type="text" name="package" required>
		<input type="submit">
	</form>
	<div id="package-info">
		<table id="package-info-table">

		</table>
	</div>
</div>

<script type="text/javascript">
	// create an array with nodes
	var nodes = [
		<?php
			$show = '';
			foreach($chart->nodes as $package) {
				$show .= '{id: "' . $package . '", label: "' . $package . '", group: "' . ($package->isPlatformPackage() ? 'gplatform' : 'gpackage') . '"},';
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
				centralGravity: 1,
				springLength: 10,
				springConstant: 0.001,
				damping: 0.005
			}
		},
		edges: {
			style: 'arrow',
			color: {
				highlight: 'red'
			}
		},
		nodes: {

		},
		groups: {
			gpackage: {
				shape: 'elipse'
			},
			gplatform: {
				shape: 'box',
				value: 20
			}
		},
		smoothCurves: false
	};
	var network = new vis.Network(container, data, options);

	createInfoBox = function (name) {
		var ajax = new XMLHttpRequest();
		ajax.onreadystatechange = function () {
			if(ajax.readyState == 4 && ajax.status == 200) {
				var data = JSON.parse(ajax.responseText);
				console.log(data.package);
				table.clear();
				table.addData('Descritpion', data.package.description);
				table.addData('Repository', data.package.repository);
				table.addData('Type', data.package.type);
				document.querySelector('#package-info-table').innerHTML = table.render();
			}
		};
		ajax.open('GET', 'packageInfo.php?package=' + name + '', true);
		ajax.send();
		return name;
	};

	network.addEventListener('select', function (props) {
		props.nodes.forEach(function (val) {
			createInfoBox(val);
		});
	});

	var table = {
		data: [],
		clear: function () {
			this.data = [];
		},
		addData: function(k, v) {
			this.data.push({key: k, value: v});
		},
		render: function() {
			var result = '';
			this.data.forEach(function (v) {
				result += '<tr><th>'+ v.key+'</th><td>'+ v.value+'</td></tr>';
			});
			return result;
		}
	};
</script>

</body>
</html>
