<?php


class AdminUtils {

    /**
     * @var string ConfigUtils和Admin的类名前缀
     */
    public static $preffix = '';

    public static function load($default = '', $key = 'get.act', $base = 'admin') {
        global $zbp;

        $type = 'request';
        if (strpos($key, '.')) {
            $type = SplitAndGet($key, '.', 0);
            $key = SplitAndGet($key, '.', 1);
        }
        $file = GetVars($key, $type);

        if (is_string($default)) {
            $default = self::_get_fullpath($default);
            if ($default !== null && is_readable($default)) {
                $default = include $default;
            }
        }

        if (empty($file) && isset($default['default'])) {
            $file = $default['default'];
        }

        if (isset($default['allows'])) {
            if (!in_array($file, $default['allows'])) {
                $zbp->ShowError(2);
            }
        } else {
            $file = FormatString($file, '[filename]');
        }

        if (isset($default['submenu'])) {
            $menus = array();
            foreach ($default['submenu'] as $id => $value) {
                if (is_string($id) && is_string($value) && in_array(strtolower($type), array('get', 'request'))) {
                    $value = array(
                        'url' => '?' . $key . '=' . $id,
                        'name' => $value
                    );
                }
                $menus[$id] = $value;
            }
            $default['submenu'] = $menus;
            if (!isset($default['nowmenu'])) {
                $default['nowmenu'] = $file;
            }
        }

        if (!isset($default['blogtitle']) && isset($default['nowmenu'])) {
            $default['blogtitle'] = $default['submenu'][$default['nowmenu']]['name'];
        }

        $is_post = count($_POST) > 0;
        $is_ajax = strtolower((string)GetVars('HTTP_X_REQUESTED_WITH', 'server')) == 'xmlhttprequest';

        if ($is_ajax) {
            CheckIsRefererValid();
            $file = 'ajax_' . $file;
        } elseif ($is_post) {
            CheckIsRefererValid();
            $file = 'post_' . $file;
        }

        $file = self::_get_fullpath($file, $base);

        if ($file === null || !is_readable($file)) {
            $zbp->ShowError(2);
        }

        if ($is_post) {
            include $file;
        } elseif ($is_ajax) {
            Add_Filter_Plugin('Filter_Plugin_Debug_Handler', get_class() . '::json_error');
            $result = include $file;
            self::json_return(0, $result);
        } else {
            $props = include $file;
            (self::$preffix . 'Admin::create')()->load($default)->load($props)->displayFull();
        }
    }

    public static function json_error($type, $error) {
        if ($type == 'Exception') {
            self::json_return(2, $error->getMessage());
        } elseif ($type == 'Error' || $type == 'Shutdown') {
            self::json_return(2, $error[1]);
        }
    }

    public static function json_return($code = 0, $data = null) {
        ob_clean();
        if ($code == 0) {
            echo json_encode(array('code' => $code, 'data' => $data));
        } else {
            echo json_encode(array('code' => $code, 'msg' => $data));
        }
        die();
    }

    public static function progress($progress, $length, $url, $style = array()) {
        $color = GetValueInArray($style, 'color', '#3a6ea5');
        $border = GetValueInArray($style, 'border', '#3a6ea5');
        $pgwidth = GetValueInArray($style, 'pgwidth', '300px');
        $text = GetValueInArray($style, 'text', '已完成(%1$d/%2$d) %3$.1f%%');

        $percent = ($progress / $length) * 100;
        $text = sprintf($text, $progress, $length, $percent);

        $html = "<div style=\"width:auto;margin:50px auto 0;padding:15px;\">" .
            "<p style=\"margin-bottom:15px;text-align:center\">$text</p>" .
            "<div style=\"width:$pgwidth;height:20px;margin:0 auto;border:1px solid $border\">" .
            "<div style=\"background:$color;height:100%;width:$percent%\"></div>" .
            "</div>" .
            "<p style=\"margin-top:15px;text-align:center\"><a href=\"$url\">若浏览器长时间没有反应，请点击此处跳转</a></p>" .
            "</div>" .
            "<script>setTimeout(function () {location.href = '$url';}, 1);</script>";
        return $html;
    }

    private static function _get_fullpath($file, $base = 'admin') {
        if (GetFileExt($file) != 'php') {
            $file .= '.php';
        }

        if (!self::_is_fullpath($file)) {
            if (strpos($file, '/')) {
                $file = plugin_dir_path(__LINE__) . $file;
            } elseif (self::_is_fullpath($base)) {
                $file = rtrim($base, '/') . '/' . $file;
            } else {
                $file = plugin_dir_path(__FILE__) . rtrim($base, '/') . '/' . $file;
            }
        }

        return $file;
    }

    private static function _is_fullpath($file) {
        if (PHP_SYSTEM == SYSTEM_WINDOWS && preg_match('/^[a-z]\:[\\\/]/i', $file)) {
            return true;
        } elseif (strpos($file, '/') === 0) {
            return true;
        }
        return false;
    }
}