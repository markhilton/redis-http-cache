<h2>Redis connection configuration</h2>

<p>Configuration parameters set by environment variables are <u>read only</u>.</p>

<table class="form-table" cellpadding="5">
    <tr>
        <th>Status:</th>
        <td>
            <label><input type="radio" name="REDIS_STATUS" value="1"  
                <?php echo rediscache::$config['REDIS_STATUS'] ? 'checked' : ''; ?>>  ON</label>  &nbsp; 
            <label><input type="radio" name="REDIS_STATUS" value="0" 
                <?php echo rediscache::$config['REDIS_STATUS'] ? '' : 'checked'; ?>> OFF</label>  &nbsp; 
        </td>
    </tr>
    <tr>
        <th>Host address:</th>
        <td>
            <input type="text" name="REDIS_HOST"
            <?php echo (isset($_ENV['REDIS_HOST']) and trim($_ENV['REDIS_HOST']) != '') ? ' readonly="true" ' : '' ?>
            value="<?php echo rediscache::$config['REDIS_HOST']; ?>" placeholder="127.0.0.1">
            <small>(default: 127.0.0.1)</small>
        </td>
    </tr>
    <tr>
        <th>Port number:</th>
        <td>
            <input type="text" name="REDIS_PORT" 
            <?php echo (isset($_ENV['REDIS_PORT']) and trim($_ENV['REDIS_PORT']) != '') ? ' readonly="true" ' : '' ?>
            value="<?php echo rediscache::$config['REDIS_PORT']; ?>" placeholder="6379">
            <small>(default Redis port is: 6379)</small>
        </td>
    </tr>
    <tr>
        <th>Connection timeout:</th>
        <td>
            <input type="text" name="REDIS_WAIT" 
            <?php echo (isset($_ENV['REDIS_WAIT']) and trim($_ENV['REDIS_WAIT']) != '') ? ' readonly="true" ' : '' ?>
            value="<?php echo rediscache::$config['REDIS_WAIT']; ?>" placeholder="1">
            <small>(recommended is 1 second)</small>
        </td>
    </tr>
    <tr>
        <th>Security password:</th>
        <td>
            <input type="text" name="REDIS_AUTH" 
            <?php echo (isset($_ENV['REDIS_AUTH']) and trim($_ENV['REDIS_AUTH']) != '') ? ' readonly="true" ' : '' ?>
            value="<?php echo rediscache::$config['REDIS_AUTH']; ?>" placeholder="">
            <small>(default: empty)</small>
        </td>
    </tr>
</table>

<p>
    <input type="submit" name="update" class="button button-primary button-large" value="Update config file"> &nbsp;
</p>