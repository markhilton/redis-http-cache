<?php

if (! rediscache::check_snippet()): ?>
	<h2>Redis cache plugin setup</h2>

	<p>Setup process requires an injection of a code line in main WordPress index.php file in your website root folder.</p>

	<code>@include 'wp-content/plugins/redis-light-speed-cache/engine.php';</code>

	<p>This will intercept all incoming requests to WordPress and route them through Redis memory cache first, before engaging WordPress.</p>

	<?php if (is_writable('../index.php')): ?>
	<br>
	<p>Click here to &nbsp; <input type="submit" name="install" value="Insert Snippet" class="button"> &nbsp; into your index.php file.</p>
	<?php endif; ?>

<?php else: ?>

	<h2>Engine snippet has been sucessfully installed.</h2>

	<p>You can append a query string to any URL to force to disengage Redis cache engine for particular page.</p>
	<ul>
		<li><b>NOCACHE</b> - render page without engaging Redis cache engine, ex: <?php echo home_url().'/?NOCACHE'; ?></li>
	</ul>

<?php endif; ?>


<?php if ($content = @file_get_contents('../index.php')): ?>
	<h3 class="top-40">Your current index.php file content</h3>
	<pre><?php echo htmlentities($content); ?></pre>
<?php endif; ?>
