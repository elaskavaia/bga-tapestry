<?php
require_once APP_GAMEMODULE_PATH . "module/table/table.game.php";
require_once "taputils.php";

abstract class tapcommon extends Table {
    protected $undoSaveOnMoveEndDup = false;
    protected array $player_colors;

    public function __construct() {
        parent::__construct();
    }

    function prepareUndoSavepoint($first = false) {
        //$undo_moves_player = $this->getGameStateValue('current_player_turn');
        if ($this->undoSaveOnMoveEndDup) {
            return;
        } // was already saved
        $this->undoSaveOnMoveEndDup = false; // clear save undo flag to send previous notification
        $this->sendNotifications();
        $partial_undo = $first ? 0 : 1;
        $move = $this->getNextMoveId();
        $this->setGameStateValue("undo_move", $move);
        $this->setGameStateValue("partial_undo", $partial_undo);
        // send undo move notification
        $this->not_a_move_notification = true;
        $this->notifyWithName("undoMove", "", ["undo_move" => $move, "partial_undo" => $partial_undo]);
        $this->sendNotifications();
        // save undo state
        $this->not_a_move_notification = false;
        // $this->debug("*** undoSavepoint $move ***");
        $this->undoSavepoint(); // that will set $this->undoSaveOnMoveEnd = true
    }

    function getNextMoveId() {
        //getGameStateValue does not work when dealing with undo, have to read directly from db
        $next_move_index = 3;
        $subsql = "SELECT global_value FROM global WHERE global_id='$next_move_index' ";
        return (int) $this->getUniqueValueFromDB($subsql);
    }

    /*
     * @Override
     * - have to override to track second copy of var flag as original one is private
     */
    function undoSavepoint() {
        parent::undoSavepoint();
        $this->undoSaveOnMoveEndDup = true;
    }

    /*
     * @Override
     * - I had to override this not fail in multiactive, it will just ignore it
     * - fixed resetting the save flag when its done
     */
    function doUndoSavePoint() {
        if (!$this->undoSaveOnMoveEndDup) {
            return;
        }
        //$this->debug("*** doUndoSavePoint ***");
        $state = $this->gamestate->state();
        if ($state["type"] == "multipleactiveplayer") {
            $name = $state["name"];
            $this->warn("using undo savepoint in multiactive state $name");
            return;
        }
        parent::doUndoSavePoint();
        $this->undoSaveOnMoveEndDup = false;
    }

    /*
     * @Override
     * fixed bug where it does not save state if there is no notifications
     */
    function sendNotifications() {
        parent::sendNotifications();
        if ($this->undoSaveOnMoveEndDup) {
            self::doUndoSavePoint();
        }
    }

    function isRealPlayer($player_id) {
        $players = $this->loadPlayersBasicInfos();
        return isset($players[$player_id]);
    }

    function isZombiePlayer($player_id) {
        $players = $this->loadPlayersBasicInfos();
        if (isset($players[$player_id])) {
            if ($players[$player_id]["player_zombie"] == 1) {
                return true;
            }
        }
        return false;
    }

    function isPlayerEliminated($player_id) {
        $players = self::loadPlayersBasicInfos();
        if (isset($players[$player_id])) {
            return $players[$player_id]["player_eliminated"] == 1;
        }
        return false;
    }

    /**
     *
     * @return integer player id based on hex $color
     */
    function getPlayerIdByColor($color) {
        $players = $this->loadPlayersBasicInfos();
        if (!isset($this->player_colors)) {
            $this->player_colors = [];
            foreach ($players as $player_id => $info) {
                $this->player_colors[$info["player_color"]] = $player_id;
            }
        }
        if (!isset($this->player_colors[$color])) {
            return 0;
        }
        return $this->player_colors[$color];
    }

    // ------ DB ----------
    function dbGetScore($player_id) {
        return $this->getUniqueValueFromDB("SELECT player_score FROM playerextra WHERE player_id='$player_id'");
    }

    function dbSetScore($player_id, $count) {
        $this->DbQuery("UPDATE playerextra SET player_score='$count' WHERE player_id='$player_id'");
        if ($this->isRealPlayer($player_id)) {
            $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$player_id'");
        }
    }

    function dbSetAuxScore($player_id, $score) {
        $this->DbQuery("UPDATE playerextra SET player_score_aux=$score WHERE player_id='$player_id'");
        if ($this->isRealPlayer($player_id)) {
            $this->DbQuery("UPDATE player SET player_score_aux=$score WHERE player_id='$player_id'");
        }
    }

    function dbIncScore($player_id, $inc) {
        $count = $this->dbGetScore($player_id);
        if ($inc != 0) {
            $count += $inc;
            $this->dbSetScore($player_id, $count);
        }
        return $count;
    }

    function dbIncStatChecked($inc, $stat, $player_id) {
        try {
            $all_stats = $this->getStatTypes();
            $player_stats = $all_stats["player"];
            if (isset($player_stats[$stat])) {
                if ($this->isRealPlayer($player_id)) {
                    $this->incStat($inc, $stat, $player_id);
                    return true;
                }
                return false;
            } else {
                $this->error("statistic $stat is not defined");
                return false;
            }
        } catch (Exception $e) {
            $this->error("error while setting statistic $stat inc $inc for $player_id");
            $this->error($e->getTraceAsString());
            return false;
        }
    }

    function debug_dumpStats($player_id = null, $stat = null) {
        if ($player_id === null || $player_id === "") {
            $player_id = $this->getActivePlayerId();
        }
        if (!$player_id) {
            return;
        }
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats["player"];
        if ($stat && isset($player_stats[$stat])) {
            $value = $this->getStat($stat, $player_id);
            $this->debugConsole("$stat=$value");
        } else {
            foreach ($player_stats as $stat => $info) {
                $value = $this->getStat($stat, $player_id);
                $this->debugConsole("$stat=$value", [], true);
            }
        }
    }

    /**
     * This will throw an exception if condition is false.
     * The message should be translated and shown to the user.
     *
     * @param $message string
     *            user side error message, translation is needed, use $this->_() when passing string to it
     * @param $cond boolean
     *            condition of assert
     * @param $log string
     *            optional log message, not need to translate
     * @throws BgaUserException
     */
    function userAssertTrue($message, $cond = false, $log = "") {
        if ($cond) {
            return;
        }
        if ($log) {
            $this->warn("$message $log|");
        }
        throw new BgaUserException(self::_($message));
    }

    /**
     * This will throw an exception if condition is false.
     * This only can happened if user hacks the game, client must prevent this
     *
     * @param $log string
     *            server side log message, no translation needed
     * @param $cond boolean
     *            condition of assert
     * @throws BgaUserException
     */
    function systemAssertTrue($log, $cond = false, $logonly = "") {
        if ($cond) {
            return;
        }
        $move = $this->getGameStateValue("next_move_id");
        $this->error("Internal Error during move $move: $log|$logonly");
        $e = new Exception($log);
        $this->error($e->getTraceAsString());
        throw new BgaUserException("Internal Error. That should not have happened. Please raise a bug.[$log]"); // NOI18N
    }

    function getMostlyActivePlayerId() {
        $state = $this->gamestate->state();
        if ($state["type"] === "multipleactiveplayer") {
            return $this->getCurrentPlayerId();
        } else {
            return $this->getActivePlayerId();
        }
    }

    function notifyWithName($type, $message = "", $args = null, $player_id = null) {
        if ($args == null) {
            $args = [];
        }
        $this->systemAssertTrue("Invalid notification signature", is_array($args));
        $private = array_get($args, "_private", false);
        unset($args["_private"]);
        if (isset($args["_notifType"])) {
            $type = $args["_notifType"];
            unset($args["_notifType"]);
        }
        if ($message) {
            $i18n = array_get($args, "i18n", []);
            foreach ($args as $arg) {
                if (!is_string($arg)) {
                    continue;
                }
                if ($arg == "player_name") {
                    continue;
                }
                if (endsWith($arg, "_name") && $arg === "name") {
                    $i18n[] = $arg;
                }
            }
            if (count($i18n) > 0) {
                $args["i18n"] = $i18n;
            }
        }
        if (array_key_exists("player_id", $args)) {
            $player_id = $args["player_id"];
        }
        if ($player_id == -1 || $player_id === null) {
            $player_id = $this->getMostlyActivePlayerId();
        }
        if (!$this->isRealPlayer($player_id)) {
            $private = false;
        }
        $args["player_id"] = $player_id;
        if ($message) {
            $player_name = $this->getPlayerNameById($player_id);
            $args["player_name"] = $player_name;
        }
        if (array_key_exists("noa", $args) || array_key_exists("nop", $args) || array_key_exists("nod", $args)) {
            $type .= "Async";
        }
        $preserve = array_get($args, "preserve", []);
        foreach ($args as $arg) {
            if (is_string($arg) && endsWith($arg, "_pv")) {
                $preserve[] = $arg;
            }
        }
        if (count($preserve) > 0) {
            $args["preserve"] = $preserve;
        }
        if ($private) {
            $this->notifyPlayer($player_id, $type, $message, $args);
        } else {
            $this->notifyAllPlayers($type, $message, $args);
        }
    }

    function getLastId($table) {
        $sql = "SELECT LAST_INSERT_ID() as res FROM $table LIMIT 1";
        return (int) $this->getUniqueValueFromDB($sql);
    }

    function extractStateOperator(&$state) {
        $op = "";
        if ($state !== null) {
            $state = trim($state);
            $matches = [];
            $res = preg_match("/^(>=|>|<=|<|<>|!=|=|) *(-?[0-9]+)$/", $state, $matches, PREG_OFFSET_CAPTURE);
            if ($res == 1) {
                $op = $matches[1][0];
                $rest = $matches[2][0];
                $state = $rest;
            }
        }
        if (!$op) {
            $op = "=";
        }
        return $op;
    }

    function queryExpression($field, $value, $value_type = 1, $expr_op = "AND") {
        if ($value === null || $value === "NULL") {
            return "";
        }
        $this->checkKey($field, false);
        $sql = " $expr_op ";
        if (is_array($value)) {
            if ($value_type == 1) {
                $this->checkArrayOfNumbers($value);
            } else {
                $this->checkArrayOfIds($value);
            }
            $sql .= "($field IN (" . implode(",", $value) . "))";
        } else {
            $percent = strpos($value, "%") !== false;
            $op = "=";
            if ($value_type != 0 && !$percent) {
                $op = $this->extractStateOperator($value);
                $this->checkValue($value, false);
            } elseif ($value_type != 0 && $percent) {
                $op = "LIKE";
                $this->checkValue($value, true);
            }
            $sql .= "$field $op '$value'";
        }
        return $sql;
    }

    final function checkNumber($info, $bThrow = true) {
        try {
            if ($info === null) {
                throw new feException("item cannot be null");
            }
            if (!is_numeric($info)) {
                throw new feException("item is not a number");
            }
            return (int) $info;
        } catch (feException $e) {
            if ($bThrow) {
                throw $e;
            }
            return false;
        }
    }

    final function checkKey($key, $like = false) {
        if ($key == null) {
            throw new feException("key cannot be null");
        }
        if (!is_string($key) && !is_numeric($key)) {
            throw new feException("key is not a string");
        }
        $extra = "";
        if ($like) {
            $extra = "%";
        }
        if (preg_match("/^[A-Za-z_0-9{$extra}]+$/", $key) == 0) {
            throw new feException("key must be alphanum and underscore non empty string '$key'");
        }
    }

    final function checkValue($key, $like = false) {
        if ($key == null) {
            throw new feException("value cannot be null");
        }
        if (!is_string($key) && !is_numeric($key)) {
            throw new feException("value is not a string");
        }
        $extra = "";
        if ($like) {
            $extra = "%";
        }
        if (preg_match("/^[-A-Za-z_0-9 :,(){$extra}]+$/", $key) == 0) {
            throw new feException("value must be alphanum and underscore non empty string '$key'");
        }
    }

    final function checkArrayOfNumbers($token_arr, $bThrow = true) {
        try {
            if ($token_arr === null) {
                throw new feException("token_arr cannot be null");
            }
            if (!is_array($token_arr)) {
                $debug = var_export($token_arr, true);
                throw new feException("token_arr is not an array: $debug");
            }
            foreach ($token_arr as $key => $info) {
                if (!is_numeric($info)) {
                    throw new feException("token_arr item is not a number");
                }
            }
            return count($token_arr);
        } catch (feException $e) {
            if ($bThrow) {
                throw $e;
            }
            return false;
        }
    }

    final function checkArrayOfIds($token_arr, $like = false, $bThrow = true) {
        try {
            if ($token_arr === null) {
                throw new feException("token_arr cannot be null");
            }
            if (!is_array($token_arr)) {
                $debug = var_export($token_arr, true);
                throw new feException("token_arr is not an array: $debug");
            }
            foreach ($token_arr as $key => $info) {
                $this->checkKey($key, false);
                $this->checkKey($info, $like);
            }
            return count($token_arr);
        } catch (feException $e) {
            if ($bThrow) {
                throw $e;
            }
            return false;
        }
    }

    /*
     * @Override to trim, because debugging does not work well with spaces (i.e. not at all).
     * cannot override debugChat because say calling it statically
     */
    function say($message) {
        $message = trim($message);
        if ($message == "DECLINE") {
            // allow to unblock in bad situations
            $this->action_unblock();
        }
        if ($this->isStudio()) {
            if ($this->debugChat($message)) {
                $message = ":$message";
            }
        }
        return parent::say($message);
    }

    function action_unblock() {
        // nothing here, override
    }

    function isStudio() {
        return $this->getBgaEnvironment() == "studio";
    }

    function isTestEnv() {
        return $this->isStudio() || $this->getGameName() == "tap" . "test"; // split like this so auto-rename won't find its
    }

    // Debug from chat: launch a PHP method of the current game for debugging purpose
    function debugChat($message) {
        $res = [];
        preg_match("/^([a-zA-Z_0-9]*) *\((.*)\)$/", $message, $res);
        if (count($res) == 3) {
            $method = $res[1];
            $args = explode(",", $res[2]);
            foreach ($args as &$value) {
                if ($value === "null") {
                    $value = null;
                } elseif ($value === "[]") {
                    $value = [];
                }
            }
            if (method_exists($this, $method)) {
                self::notifyAllPlayers("simplenotif", "DEBUG: calling $message", []);
                $ret = call_user_func_array([$this, $method], $args);
                if ($ret !== null) {
                    if (is_scalar($ret)) {
                        $retval = $ret;
                    } else {
                        $retval = "arr";
                    }
                    $this->debugConsole("RETURN: $method -> $retval", ["ret" => $ret]);
                }
                return true;
            } else {
                self::notifyPlayer(
                    $this->getCurrentPlayerId(),
                    "simplenotif",
                    "DEBUG: running $message; Error: method $method() does not exists",
                    []
                );
                return true;
            }
        }
        return false;
    }

    function debugConsole($info, $args = [], $silent = false) {
        if ($silent) {
            // silent log
            $args["log"] = $info;
            $this->notifyAllPlayers("log", "", $args);
        } else {
            $this->notifyAllPlayers("log", $info, $args);
        }
        $this->warn($info);
    }

    /**
     * This to make it public
     */
    public function _($text) {
        return parent::_($text);
    }
}
