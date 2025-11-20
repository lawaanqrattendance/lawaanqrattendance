<?php
function checkRateLimit($key, $limit = 60, $window = 3600) {
    $file = sys_get_temp_dir() . '/rate_' . md5($key);
    
    $current = 1;
    if (file_exists($file)) {
        $data = unserialize(file_get_contents($file));
        if (time() - $data['time'] < $window) {
            $current = $data['count'] + 1;
        }
    }
    
    file_put_contents($file, serialize(['count' => $current, 'time' => time()]));
    return $current <= $limit;
} 