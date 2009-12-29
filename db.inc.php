<?php

require_once('config.inc.php');
require_once('user.inc.php');

class db extends mysqli {

    public static function check_creds($host, $base, $user, $pass) {
        $temp = new mysqli();
        @$temp->connect($host, $user, $pass);
        if ($temp->connect_error) {
            return 'could not connect to database server';
        }
        if ($base != '' && !$temp->select_db($base)) {
            return 'could not select database';
        }
        return NULL;
    }

    public function __construct() {
        parent::__construct(DB_HOST, DB_USER, DB_PASS);
        if ($this->connect_errno) {
            $this->fail('could not connect to database server');
        }
        $this->query("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
        $this->query("SET @@time_zone = 'Europe/Berlin'");
        if (!$this->select_db(DB_BASE)) {
            if (!$this->create_db()) {
                $this->fail('could not create database'); // need database or rights to create it
            }
        }
        if (FIRST_RUN) {
            $this->reset_tables();
            $config = file_get_contents('config.inc.php');
            $config = preg_replace('/(?<=define\(\'FIRST_RUN\', )true(?=\);)/i', 'false', $config, 1);
            file_put_contents('config.inc.php', $config);
        }
    }

    // checks if the current user's ip address matches the one in the database
    public function session_ok() {
        $result = $this->query(
           "SELECT
                `ip1`,
                `ip2`
            FROM `user` WHERE
                `sid`  = '".$this->protect(session_id())."'
            LIMIT 1"
        );
        if (!($row = $result->fetch_assoc())) {
            return false;
        }
        if ($row['ip2'] != NULL) {
            $ip = ($row['ip2'] << 64) + $row['ip1'];
        } else {
            $ip = $row['ip1'];
        }
        return $ip == ip2long($_SERVER['REMOTE_ADDR']);
    }

    public function login($name, $pwd) {
        $result = $this->query(
           "SELECT
                `id`,
                `privilege`
            FROM `user` WHERE
                `name`      = '".$this->protect($name)."' AND
                `pwd_hash`  = '".$this->protect(hash('sha256', $pwd))."'"
        );
        if (!($row = $result->fetch_assoc())) {
            return -1; // user not found or wrong password
        }
        $ip = ip2long($_SERVER['REMOTE_ADDR']);
        $ip1 = $ip & 0xFFFFFFFFFFFFFFFF;
        $ip2 = $ip >> 64;
        $this->query(
           "UPDATE `user` SET
                `ip1` = '".$this->protect($ip1)."',
                `ip2` = '".$this->protect($ip2)."',
                `sid` = '".$this->protect(session_id())."'
            WHERE
                `id` = '".$this->protect($row['id'])."'
            LIMIT 1"
        );
        return $row['privilege']; // privilege is always positive
    }

    public function logout() {
        $this->query(
           "UPDATE `user` SET
                `ip1` = NULL,
                `ip2` = NULL,
                `sid` = NULL
            WHERE
                `sid` = '".$this->protect(session_id())."'
            LIMIT 1"
        );
        return $this->affected_rows == 1;
    }

    public function query($query) {
        if (!($result = parent::query($query))) {
            if (DEBUG) {
                $this->fail($this->error);
            } else {
                $this->fail('invalid SQL query syntax');
            }
        }
        return $result;
    }

    public function protect($str) {
        return $this->escape_string(htmlspecialchars($str));
    }

    private function create_db() {
        if ($this->query("CREATE DATABASE `".$this->protect(DB_BASE)."` CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci'") === false) {
            return false;
        }
        return $this->select_db(DB_BASE);
    }

    private function reset_tables() {
        /*
        This table holds the user data of all the students who have access.
        id:        unique user id used to identify user during their session
        name:      user name, e.g. 'jdoe' FIXME: Unique?
        pwd_hash:  sha256-hashed password
        privilege: privilege level
                     0 - no rights whatsoever (useful for suspending accounts)
                     1 - view all data except for teacher names, default (students)
                     2 - view all data (teachers)
                     3 - view all data, and modify entries (Mrs. Lange I)
                     4 - view all data, modify entries, and add new users (root)
        ip1, ip2: the current IPv6 address if the user is logged in
        */
        $this->query("DROP TABLE IF EXISTS `user`");
        $this->query("CREATE TABLE `user` (
            `id`        INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name`      VARCHAR(20)      NOT NULL,
            `pwd_hash`  CHAR(64)         NOT NULL,
            `privilege` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `ip1`       BIGINT UNSIGNED  NULL     DEFAULT NULL,
            `ip2`       BIGINT UNSIGNED  NULL     DEFAULT NULL,
            `sid`       INT UNSIGNED     NULL     DEFAULT NULL)"
        );

        /*
        This table holds the timetable changes (including the good stuff such as cancelled classes...)
        id:       unique entry id used to identify an entry during modification
        time:     timestamp of the day and time the class would normally start (e.g. Friday, July 13th)
        teacher:  name of the absent teacher (e.g. Mr. Doe)
        subject:  name and type of the course or subject (e.g. Ma-LK)
        duration: new duration of this class in minutes (e.g. 75)
        course:   name of the course (e.g. '9.3')
        oldroom:  room the class was supposed to take place in originally (e.g. H2-3)
        sub:      name of the substitute teacher (e.g. 'Fr. Musterfrau')
        change:   what class takes place [where] instead (e.g. 'Geschichte H0-2' or 'Ausfall')
        */
        $this->query("DROP TABLE IF EXISTS `entry`");
        $this->query("CREATE TABLE `entry` (
            `id`       INT UNSIGNED      NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `teacher`  VARCHAR(30)       NULL     DEFAULT NULL,
            `time`     TIMESTAMP         NULL     DEFAULT NULL,
            `course`   VARCHAR(5)        NULL     DEFAULT NULL,
            `subject`  VARCHAR(5)        NULL     DEFAULT NULL,
            `duration` SMALLINT UNSIGNED NULL     DEFAULT NULL,
            `sub`      VARCHAR(30)       NULL     DEFAULT NULL,
            `change`   VARCHAR(40)       NULL     DEFAULT NULL,
            `oldroom`  VARCHAR(5)        NULL     DEFAULT NULL,
            `newroom`  VARCHAR(5)        NULL     DEFAULT NULL)"
        );
        ovp_user::add($this, 'admin', ADMIN_PWD, 'admin');
    }

    // FIXME: maybe use fail() from misc.inc.php instead?
    private function fail($msg) {
        die('ERROR: '.$msg);
    }
}
?>
