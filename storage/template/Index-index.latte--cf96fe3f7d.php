<?php
// source: Panel/Index/index.latte

use Hail\Latte\Runtime as LR;

class Templatecf96fe3f7d extends Hail\Latte\Runtime\Template
{

	function main()
	{
		extract($this->params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
test
</body>
</html><?php
		return get_defined_vars();
	}

}
