<h2><?php echo $title; ?></h2>

<?php foreach ($stock as $stock_item): ?>

        <h3><?php echo $stock_item['quantity']; ?></h3>
        <div class="main">
                <?php echo $stock_item['units']; ?>
        </div>
        <p><a href="<?php echo site_url('stock/'.$stock_item['id']); ?>">View item</a></p>

<?php endforeach; ?>
