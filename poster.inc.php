<?php

/**
 * This file is part of RLO-Plan.
 *
 * Copyright 2009 Tillmann Karras, Josua Grawitter
 *
 * RLO-Plan is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * RLO-Plan is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with RLO-Plan.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once('db.inc.php');
require_once('user.inc.php');
require_once('misc.inc.php');
require_once('entry.inc.php');
require_once('logger.inc.php');

abstract class poster {
    public static $priv_req;
    abstract public function evaluate($post);

}

class post_user extends poster {
    public static $priv_req = ovp_logger::VIEW_ADMIN;
    private $db;

    public function __construct(db $db) {
        $this->db = $db;
    }

    public function evaluate($post) {
        switch ($post['action']) {
        case 'add':
            if (isset($post['name']) && isset($post['password']) && isset($post['role'])) {
                $id = ovp_user::add($this->db, $post['name'], $post['password'], $post['role']);
                if ($id){
                    exit($id);
                } else {
                    fail('Hinzufügen gescheitert');
                }
            }
            fail('Daten unvollständig');
        case 'update':
            if (!isset($post['id'])) {
                fail('ID fehlt');
            }
            $user = new ovp_user($this->db, $post['id']);
            $result = true;
            foreach ($post as $key => $value) {
                switch ($key) {
                case 'id':
                case 'action':
                    break;
                case 'name':
                    $result = $user->set_name($value);
                    break;
                case 'password':
                    $result = $user->set_password($value);
                    break;
                case 'role':
                    $result = $user->set_role($value);
                    break;
                default:
                    // DoNothing (tm)
                }
                if (!($result)) {
                    fail('Änderung gescheitert');
                }
            }
            exit('updated');
        case 'delete':
            if (!isset($post['id'])) {
                fail('ID fehlt');
            } else if (ovp_user::remove($this->db, $post['id'])) {
                exit('deleted');
            }
            fail('ID ungültig');
        default:
            fail('Ungültige Anfrage');
        }
    }
}

class post_password extends poster {
    public static $priv_req = ovp_logger::PRIV_LOGIN;
    private $db;

    public function __construct(db $db) {
        $this->db = $db;
    }

    public function evaluate($post) {
        if (isset($post['newpwd']) && isset($post['oldpwd'])) {
            $user = ovp_logger::get_current_user($this->db);
            if ($user->check_password($post['oldpwd'])) {
                if ($user->set_password($post['newpwd'])) {
                    exit('updated');
                } else {
                    fail('Passwort ändern gescheitert');
                }
            } else {
                fail('Altes Password inkorrekt');
            }
        } else {
            fail('Daten unvollständig');
        }
    }
}

class post_login extends poster {
    public static $priv_req = ovp_logger::PRIV_LOGOUT;
    private $db;

    public function __construct(db $db) {
        $this->db = $db;
    }

    public function evaluate($post) {
        if (isset($post['name']) && isset($post['pwd'])) {
            $_SESSION['privilege'] = ovp_logger::login($this->db, $post['name'], $post['pwd']);
            if ($_SESSION['privilege'] != -1) {
                ovp_logger::redirect($_GET['continue']);
            }
        }
        ovp_logger::redirect('index.php?source=login&attempt=failed&continue='.urlencode($_GET['continue']));
    }
}

class post_logout extends poster {
    public static $priv_req = ovp_logger::PRIV_LOGIN;
    private $db;

    public function __construct(db $db) {
        $this->db = $db;
    }

    public function evaluate($post) {
        ovp_logger::logout($this->db);
        $_SESSION = array();
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        session_destroy();
        ovp_logger::redirect('index.php');
    }
}

class post_entry extends poster {
    public static $priv_req = ovp_logger::VIEW_AUTHOR;
    private $db;

    public function __construct(db $db) {
        $this->db = $db;
    }

    public function evaluate($post) {
        switch ($post['action']) {
        case 'add':
            if (!(isset($post['date'])  && isset($post['teacher'])  &&
                isset($post['time'])    && isset($post['course'])   &&
                isset($post['subject']) && isset($post['duration']) &&
                isset($post['sub'])     && isset($post['change'])   &&
                isset($post['oldroom']) && isset($post['newroom']))) {
                fail('Daten unvollständig');
            }
            if (ovp_entry::add($this->db, $post)) {
                exit('success');
            }
            fail('Hinzufügen gescheitert');
        case 'update':
            if (!(isset($post['id'])    &&
                isset($post['date'])    && isset($post['teacher'])  &&
                isset($post['time'])    && isset($post['course'])   &&
                isset($post['subject']) && isset($post['duration']) &&
                isset($post['sub'])     && isset($post['change'])   &&
                isset($post['oldroom']) && isset($post['newroom']))) {
                fail('Daten unvollständig');
            }
            $entry = new ovp_entry($this->db, $post['id']);
            if ($entry->set_values($post)) {
                exit('updated');
            } else {
                fail('Änderung gescheitert');
            }
        case 'delete':
            if (!isset($post['id'])) {
                fail('ID fehlt');
            }
            if (!ovp_entry::remove($this->db, $post['id'])) {
                fail('ID ungültig');
            } else {
                exit('deleted');
            }
        default:
            fail('Ungültige Anfrage');
        }
    }
}

class post_mysql extends poster {
    public static $priv_req = ovp_logger::VIEW_ADMIN;

    public function evaluate($post) {
        if (!(isset($post['host']) && isset($post['base']) && isset($post['user']) && isset($post['pass']))) {
            fail('Daten unvollständig');
        }
        $config = new ovp_config();
        $config->set('DB_HOST', "'".$post['host']."'");
        $config->set('DB_BASE', "'".$post['base']."'");
        $config->set('DB_USER', "'".$post['user']."'");
        $config->set('DB_PASS', "'".$post['pass']."'");
        if ($error = db::check_creds($post['host'], $post['base'], $post['user'], $post['pass'])) {
            $link = get_link('mysql&error='.urlencode($error));
        } else {
            //FIXME: HACKHACK
            define('DB_HOST', $post['host']);
            define('DB_BASE', $post['base']);
            define('DB_USER', $post['user']);
            define('DB_PASS', $post['pass']);
            new db()->reset_tables();
            if (WIZARD) {
                $link = get_link('settings');
            } else {
                $link = get_link('mysql');
            }
        }
        ovp_logger::redirect($link);
    }
}

class post_settings extends poster {
    public static $priv_req = ovp_logger::VIEW_ADMIN;

    public function evaluate($post) {
        if (!(isset($post['debug']) && isset($post['delold']) && isset($post['skipweekends']) && isset($post['privdefault']))) {
            fail('Daten unvollständig');
        }
        $config->set('DEBUG',             $post['debug']);
        $config->set('DELETE_OLDER_THAN', $post['delold']);
        $config->set('SKIP_WEEKENDS',     $post['skipweekends']);
        $config->set('PRIV_DEFAULT',      $post['privdefault']);
        if (WIZARD) {
            $link = get_link('account');
        } else {
            $link = get_link('settings');
        }
        ovp_logger::redirect($link);
    }
}

class post_account extends poster {
    public static $priv_req = ovp_logger::VIEW_ADMIN;
    private $db;

    public function __construct(db $db) {
        $this->db = $db;
    }

    public function evaluate($post) {
        if (!(isset($post['name']) && isset($post['pwd']))) {
            fail('Daten unvollständig');
        }
        if (ovp_user::name_exists($this->db, $post['name'])) {
            $user = ovp_user::get_user_by_name($this->db, $post['name']);
            $user->set_password($post['pwd']);
        } else {
            ovp_user::add($this->db, $post['name'], $post['pwd'], 'admin');
        }
        if (WIZARD) {
            $link = get_link('final');
        } else {
            $link = get_link('account');
        }
        ovp_logger::redirect($link);
    }
}

?>