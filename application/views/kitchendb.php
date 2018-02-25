<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php 
foreach($css_files as $file): ?>
	<link type="text/css" rel="stylesheet" href="<?php echo $file; ?>" />
<?php endforeach; ?>
<?php foreach($js_files as $file): ?>
	<script src="<?php echo $file; ?>"></script>
<?php endforeach; ?>
</head>
<body>
	<div>
 		<a href='<?php echo site_url('kitchendb/ingredients')?>'>Ingredients</a> |
        <a href='<?php echo site_url('kitchendb/stock')?>'>Stock</a> |
        <a href='<?php echo site_url('kitchendb/manage')?>'>Manage Raw Ingredients</a> |
        <a href='<?php echo site_url('kitchendb/recipes')?>'>Recipes</a> |
        <a href='<?php echo site_url('kitchendb/batch')?>'>Batches</a> |
        <a href='<?php echo site_url('kitchendb/temperatures')?>'>Temperature Records</a> |
        <a href='<?php echo site_url('admin/user/logout')?>'>Logout</a>
	</div>
	<div style='height:20px;'></div>  
    <div>
		<?php echo $output; ?>
    </div>
</body>
</html>
