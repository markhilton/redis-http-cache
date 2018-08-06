<h2>URL Exceptions</h2>

<p>You can specify list of URLs to explicitly force Redis engine not to cache its contents. One URL per line.</p>
<p>Do not include host name in the URL string. <i>Example (2 URLs to be excluded from caching):</i></p>

<p><code>/some-page/</code></p>
<p><code>/category/other-post/</code></p>


<p></p>
<p><textarea cols="80" rows="10" name="REDIS_EXCLUDE"><?php echo join("\r\n", rediscache::$config['REDIS_EXCLUDE']); ?></textarea></p>
<p>
	Ignore query parameters: &nbsp;
    <input type="radio" name="REDIS_QUERY" value="1" <?php echo rediscache::$config['REDIS_QUERY'] ? 'checked' : ''; ?>> YES
    <input type="radio" name="REDIS_QUERY" value="0"  <?php echo rediscache::$config['REDIS_QUERY'] ? '' : 'checked'; ?>> NO
</p>

<p><input type="submit" name="exclude" class="button button-primary button-large" value="Exclude"></p>
