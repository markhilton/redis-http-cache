<h2>Redis connection configuration</h2>

<table class="form-table" cellpadding="5">
    <tr>
        <th>Status:</th>
        <td>
            <label><input type="radio" name="status" value="ON"  <?php echo rediscache::$config['status'] == 'ON' ? 'checked' : ''; ?>>  ON</label>  &nbsp; 
            <label><input type="radio" name="status" value="OFF" <?php echo rediscache::$config['status'] != 'ON' ? 'checked' : ''; ?>> OFF</label>  &nbsp; 
        </td>
    </tr>
    <tr>
        <th>Host address:</th>
        <td>
            <input type="text" name="host" value="<?php echo rediscache::$config['host']; ?>" placeholder="127.0.0.1">
            <small>(default: 127.0.0.1)</small>
        </td>
    </tr>
    <tr>
        <th>Port number:</th>
        <td>
            <input type="text" name="port" value="<?php echo rediscache::$config['port']; ?>" placeholder="6379">
            <small>(default Redis port is: 6379)</small>
        </td>
    </tr>
    <tr>
        <th>Connection timeout:</th>
        <td>
            <input type="text" name="timeout" value="<?php echo rediscache::$config['timeout']; ?>" placeholder="1">
            <small>(recommended is 1 second)</small>
        </td>
    </tr>
    <tr>
        <th>Security password:</th>
        <td>
            <input type="text" name="security" value="<?php echo rediscache::$config['security']; ?>" placeholder="">
            <small>(default: empty)</small>
        </td>
    </tr>
</table>

<p>
    <input type="submit" name="update"   class="button button-primary button-large" value="Update config file"> &nbsp;
    <input type="submit" name="defaults" class="button button-primary button-large" value="Load default config">
</p>