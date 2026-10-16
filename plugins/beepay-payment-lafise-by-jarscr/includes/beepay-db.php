<?php
/**
 * Capa de base de datos Beepay sobre WordPress ($wpdb).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('beepay_transactions_table')) {
    function beepay_transactions_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'beepay_transactions';
    }
}

if (!function_exists('beepay_create_tables')) {
    function beepay_create_tables()
    {
        global $wpdb;

        $table           = beepay_transactions_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            orden bigint(20) DEFAULT NULL,
            date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cuenta varchar(64) DEFAULT NULL,
            authCode varchar(64) DEFAULT NULL,
            rrn varchar(64) DEFAULT NULL,
            intRef varchar(64) DEFAULT NULL,
            merchTokenId varchar(128) DEFAULT NULL,
            eci varchar(32) DEFAULT NULL,
            ipsRc varchar(32) DEFAULT NULL,
            threeDSSessionData longtext DEFAULT NULL,
            marca varchar(64) DEFAULT NULL,
            monto varchar(32) DEFAULT NULL,
            refund varchar(32) DEFAULT NULL,
            refundDate datetime DEFAULT NULL,
            refundStatus varchar(32) DEFAULT NULL,
            refundComments longtext DEFAULT NULL,
            complete varchar(32) DEFAULT NULL,
            completeDate datetime DEFAULT NULL,
            completeStatus varchar(32) DEFAULT NULL,
            completeComments longtext DEFAULT NULL,
            orderId varchar(64) DEFAULT NULL,
            cuotas varchar(16) DEFAULT NULL,
            currency varchar(8) DEFAULT NULL,
            status varchar(8) DEFAULT NULL,
            nombre varchar(191) DEFAULT NULL,
            apellido varchar(191) DEFAULT NULL,
            telefono varchar(64) DEFAULT NULL,
            email varchar(191) DEFAULT NULL,
            comments longtext DEFAULT NULL,
            retorno longtext DEFAULT NULL,
            cancelar longtext DEFAULT NULL,
            hash varchar(128) DEFAULT NULL,
            ip varchar(64) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY orden (orden),
            KEY status (status),
            KEY date (date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

if (!class_exists('Beepay_DB_Statement')) {
    class Beepay_DB_Statement
    {
        private $wpdb;
        private $sql;
        private $rows_assoc = array();
        private $rows_obj   = array();
        private $row_index  = 0;

        public function __construct($wpdb, $sql)
        {
            $this->wpdb = $wpdb;
            $this->sql  = $sql;
        }

        private function convert_placeholders($sql)
        {
            if (strpos($sql, '?') === false) {
                return $sql;
            }

            $parts  = explode('?', $sql);
            $result = array_shift($parts);

            foreach ($parts as $part) {
                $result .= '%s' . $part;
            }

            return $result;
        }

        public function execute($params = array())
        {
            $this->rows_assoc = array();
            $this->rows_obj   = array();
            $this->row_index  = 0;

            if (strpos($this->sql, '?') !== false) {
                $prepared = $this->wpdb->prepare($this->convert_placeholders($this->sql), $params);
            } else {
                $prepared = $this->sql;
            }

            $sql_trim = ltrim($this->sql);

            if (stripos($sql_trim, 'SELECT') === 0) {
                $this->rows_assoc = $this->wpdb->get_results($prepared, ARRAY_A);
                $this->rows_obj   = $this->wpdb->get_results($prepared);

                if ($this->rows_assoc === null) {
                    $this->rows_assoc = array();
                }
                if ($this->rows_obj === null) {
                    $this->rows_obj = array();
                }

                return true;
            }

            $result = $this->wpdb->query($prepared);

            if ($result === false) {
                throw new Exception($this->wpdb->last_error);
            }

            return true;
        }

        public function fetch($mode = PDO::FETCH_ASSOC)
        {
            if ($mode === PDO::FETCH_OBJ) {
                if (!isset($this->rows_obj[$this->row_index])) {
                    return false;
                }

                return $this->rows_obj[$this->row_index++];
            }

            if (!isset($this->rows_assoc[$this->row_index])) {
                return false;
            }

            return $this->rows_assoc[$this->row_index++];
        }

        public function fetchAll($mode = PDO::FETCH_ASSOC)
        {
            if ($mode === PDO::FETCH_OBJ) {
                return $this->rows_obj;
            }

            return $this->rows_assoc;
        }
    }
}

if (!class_exists('Beepay_DB')) {
    class Beepay_DB
    {
        private $wpdb;
        private $table;

        public function __construct()
        {
            global $wpdb;
            $this->wpdb  = $wpdb;
            $this->table = beepay_transactions_table();
        }

        private function map_sql($sql)
        {
            return str_replace('beepay_transactions', $this->table, $sql);
        }

        public function prepare($sql)
        {
            return new Beepay_DB_Statement($this->wpdb, $this->map_sql($sql));
        }

        public function exec($sql)
        {
            $result = $this->wpdb->query($this->map_sql($sql));

            if ($result === false) {
                throw new Exception($this->wpdb->last_error);
            }

            return $result;
        }
    }
}

if (!function_exists('beepay_get_db')) {
    function beepay_get_db()
    {
        static $db = null;

        if ($db === null) {
            beepay_create_tables();
            beepay_migrate_sqlite_to_wp();
            $db = new Beepay_DB();
        }

        return $db;
    }
}

if (!function_exists('beepay_migrate_sqlite_to_wp')) {
    function beepay_migrate_sqlite_to_wp()
    {
        global $wpdb;

        if (get_option('beepay_sqlite_migrated')) {
            return;
        }

        $sqlite_path = plugin_dir_path(dirname(__FILE__)) . 'db/beepay.db';
        $table       = beepay_transactions_table();
        $count       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($count > 0) {
            update_option('beepay_sqlite_migrated', 1, false);
            return;
        }

        if (!file_exists($sqlite_path)) {
            update_option('beepay_sqlite_migrated', 1, false);
            return;
        }

        try {
            $sqlite = new PDO('sqlite:' . $sqlite_path, '', '', array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ));
        } catch (Exception $e) {
            error_log('Beepay SQLite migration skipped: ' . $e->getMessage());
            return;
        }

        $rows = $sqlite->query('SELECT * FROM beepay_transactions')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            unset($row['id']);

            if (!empty($row['date'])) {
                $row['date'] = beepay_normalize_datetime($row['date']);
            }
            if (!empty($row['refundDate'])) {
                $row['refundDate'] = beepay_normalize_datetime($row['refundDate']);
            }
            if (!empty($row['completeDate'])) {
                $row['completeDate'] = beepay_normalize_datetime($row['completeDate']);
            }

            $wpdb->insert($table, $row);
        }

        update_option('beepay_sqlite_migrated', 1, false);
    }
}

if (!function_exists('beepay_normalize_datetime')) {
    function beepay_normalize_datetime($value)
    {
        $value = trim(str_replace(',', '', $value));
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return current_time('mysql');
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
