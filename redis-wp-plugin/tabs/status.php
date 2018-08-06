<h2>Redis status</h2>

<table class="form-table">
    <tr>
        <th>Status:</th>
        <td>
            <?php if (rediscache::$config['REDIS_STATUS']): ?>
            <span class="green">[ ENGAGED ]</span>
            <?php else: ?>
            <span class="red">[ DISENGAGED ]</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Stored pages:</th>
        <td><?php echo number_format(rediscache::$info['pages'], 0); ?></td>
    </tr>
    <tr>
        <th>Version:</th>
        <td><?php echo rediscache::$info['redis_version']; ?></td>
    </tr>
    <tr>
        <th>Memory used / peak:</th>
        <td><?php echo rediscache::$info['used_memory_human'].' / '.rediscache::$info['used_memory_peak_human']; ?></td>
    </tr>
    <tr>
        <th>Uptime:</th>
        <td><?php printf('%d days, %d hours and %s minutes', gmdate('d', rediscache::$info['uptime_in_seconds']), gmdate('H', rediscache::$info['uptime_in_seconds']), gmdate('m', rediscache::$info['uptime_in_seconds'])); ?></td>
    </tr>
</table>

<p><input type="submit" name="flush" class="button button-primary button-large" value="Flush cache"></p>