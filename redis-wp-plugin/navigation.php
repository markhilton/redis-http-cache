<link rel="stylesheet" type="text/css" href="<?php echo plugin_dir_url( __FILE__ ); ?>stylesheet.css">

<script src="<?php echo plugin_dir_url( __FILE__ ); ?>scripts.js"></script>

<div class="wrap">
    <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">

    <div class="spacer">
        <h1>Redis Light Speed Caching Engine for WordPress</h1>
        <p>Redis Light Speed is a light weight caching engine for WordPress, which inserts itself before WordPress starts processing incoming URL requests.</p>
    </div>

    <?php $current = isset($_GET['tab']) ? $_GET['tab'] : 'status'; ?>

    <div id="tabs">
        <h2 class="nav-tab-wrapper">
            <a class="nav-tab <?php echo $current == 'status'     ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=rediscache'); ?>&tab=status">Status</a>
            <a class="nav-tab <?php echo $current == 'exceptions' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=rediscache'); ?>&tab=exceptions">Exceptions</a>
            <a class="nav-tab <?php echo $current == 'config'     ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=rediscache'); ?>&tab=config">Config</a>
            <?php /* <a class="nav-tab <?php echo $current == 'setup'      ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=rediscache'); ?>&tab=setup">Setup</a> */ ?>
        </h2>

        <?php @include sprintf('tabs/%s.php', $current); ?>
    </div>

    </form>
</div>
