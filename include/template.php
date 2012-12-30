<!DOCTYPE html>
<html>
<head>
<title>
<?php
echo strip_tags($project->title());
?>
</title>
<link rel="stylesheet" type="text/css" href="include/static/style.css" />
<link rel="stylesheet" type="text/css" href="include/static/projects.css" />
<script type="text/javascript" src="include/static/jquery.js"></script>
<script type="text/javascript" src="include/static/main.js"></script>

<meta charset="utf-8" />
</head>
<body>



<?php
	echo '<div id="top">';
	echo $project->title();
	echo '</div>';

	echo '<div id="toolbar">';
	$project->Toolbar();
	echo '</div>';

	echo '<div id="content"><div>';
	$project->Content();
	echo '</div></div>';
?>

<?php //$project->GetTime();
?>
</body>
</html>
