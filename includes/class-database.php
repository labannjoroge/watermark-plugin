<?php

namespace WatermarkManager\Includes;

class Database {
    private static $table_name = 'WM_settings';

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_name varchar(255) NOT NULL,
            setting_value longtext NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_name (setting_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function drop_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    public static function get_setting($name) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_name WHERE setting_name = %s",
            $name
        ));
        return $result ? maybe_unserialize($result) : false;
    }

    public static function update_setting($name, $value) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        $serialized_value = maybe_serialize($value);
        
        $existing = self::get_setting($name);
        if ($existing === false) {
            return $wpdb->insert(
                $table_name,
                array('setting_name' => $name, 'setting_value' => $serialized_value),
                array('%s', '%s')
            );
        } else {
            return $wpdb->update(
                $table_name,
                array('setting_value' => $serialized_value),
                array('setting_name' => $name),
                array('%s'),
                array('%s')
            );
        }
    }
}

