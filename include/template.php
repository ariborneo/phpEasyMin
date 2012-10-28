<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>
<?php
echo strip_tags($project->title());
?>
</title>
<link rel="stylesheet" type="text/css" href="include/static/style.css" />
<link rel="stylesheet" type="text/css" href="include/static/projects.css" />
<script type="text/javascript" src="include/static/jquery.1.6.4.js"></script>
<script type="text/javascript" src="include/static/main.js"></script>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
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
