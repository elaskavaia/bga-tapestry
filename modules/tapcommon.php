<?php

use Bga\GameFramework\Table;
use Bga\GameFramework\UserException;
use Bga\GameFramework\VisibleSystemException;

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
    function undoSavepoint(): void {
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
        if ($this->gamestate->isMultiactiveState()) {
            $name = $this->gamestate->getCurrentMainState()?->name;
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
        $sc = "player_" . "score"; // to trick the scanner
        return $this->getUniqueValueFromDB("SELECT $sc FROM playerextra WHERE player_id='$player_id'");
    }

    function dbSetScore($player_id, $count) {
        $sc = "player_" . "score";
        $this->DbQuery("UPDATE playerextra SET $sc='$count' WHERE player_id='$player_id'");
        if ($this->isRealPlayer($player_id)) {
            $this->bga->playerScore->set((int) $player_id, (int) $count, null);
        }
    }

    function dbSetAuxScore($player_id, $score) {
        $sc = "player_" . "score_aux";
        $this->DbQuery("UPDATE playerextra SET $sc=$score WHERE player_id='$player_id'");
        if ($this->isRealPlayer($player_id)) {
            $this->bga->playerScoreAux->set((int) $player_id, (int) $score, null);
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

    function hasPlayerStat(string $stat): bool {
        return isset($this->getStatTypes()["player"][$stat]);
    }

    function dbIncStatChecked($inc, $stat, $player_id) {
        try {
            if ($this->hasPlayerStat($stat)) {
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
            $this->logStackTrace($e->getMessage());
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
     *            user side error message, translation is needed, wrap it in clienttranslate()
     * @param $cond boolean
     *            condition of assert
     * @param $log string
     *            optional log message, not need to translate
     * @throws UserException
     */
    function userAssertTrue($message, $cond = false, $log = "") {
        if ($cond) {
            return;
        }
        if ($log) {
            $this->warn("$message $log|");
        }
        throw new UserException($message);
    }

    /** Where we are, as a log line. */
    function logStackTrace($message) {
        $this->error((new Exception($message))->getTraceAsString());
    }

    /**
     * Throws if the condition is false. Only reachable if the client lets an illegal action through.
     *
     * @param $log string shown to the player, no translation needed
     * @param $logonly string server log only
     * @throws VisibleSystemException
     */
    function systemAssertTrue(string $log, mixed $cond = false, string $logonly = ""): void {
        if ($cond) {
            return;
        }

        $this->error("Internal Error: $log|$logonly|" . $this->getSystemAssertContext());
        // Visible so players can quote it in a report, system so it reaches the studio error log
        throw new VisibleSystemException("Internal Error. Please raise a bug.[$log]"); // NOI18N
    }

    function getSystemAssertContext(): string {
        $move = $this->getGameStateValue("next_move_id");
        return "move$move state" . $this->gamestate->getCurrentMainStateId();
    }

    /**
     * Wrapper for bga_rand, tests override it to make randomness deterministic.
     */
    function bgaRand(int $min, int $max): int {
        return bga_rand($min, $max);
    }

    function getMostlyActivePlayerId() {
        if ($this->gamestate->isMultiactiveState()) {
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
            $player_name = $this->customGetPlayerNameById((int) $player_id);
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

    public function customGetPlayerNameById($player_id): string {
        if ($player_id == PLAYER_AUTOMA) {
            return clienttranslate("Automa");
        }
        if ($player_id == PLAYER_SHADOW) {
            return clienttranslate("Shadow Empire");
        }

        if (!$this->isRealPlayer($player_id)) {
            return clienttranslate("Unknown"); // should not happen
        }

        return $this->getPlayerNameById((int) $player_id);
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
                throw new VisibleSystemException("item cannot be null");
            }
            if (!is_numeric($info)) {
                throw new VisibleSystemException("item is not a number");
            }
            return (int) $info;
        } catch (VisibleSystemException $e) {
            if ($bThrow) {
                throw $e;
            }
            return false;
        }
    }

    final function checkKey($key, $like = false) {
        if ($key == null) {
            throw new VisibleSystemException("key cannot be null");
        }
        if (!is_string($key) && !is_numeric($key)) {
            throw new VisibleSystemException("key is not a string");
        }
        $extra = "";
        if ($like) {
            $extra = "%";
        }
        if (preg_match("/^[A-Za-z_0-9{$extra}]+$/", $key) == 0) {
            throw new VisibleSystemException("key must be alphanum and underscore non empty string '$key'");
        }
    }

    final function checkValue($key, $like = false) {
        if ($key == null) {
            throw new VisibleSystemException("value cannot be null");
        }
        if (!is_string($key) && !is_numeric($key)) {
            throw new VisibleSystemException("value is not a string");
        }
        $extra = "";
        $check = $key;
        if ($like) {
            $extra = "%";
            // \_ and \% are LIKE escapes, a backslash anywhere else stays illegal
            $check = preg_replace("/\\\\[_%]/", "", $key);
        }
        if (preg_match("/^[-A-Za-z_0-9 :,(){$extra}]+$/", $check) == 0) {
            throw new VisibleSystemException("value must be alphanum and underscore non empty string '$key'");
        }
    }

    final function checkArrayOfNumbers($token_arr, $bThrow = true) {
        try {
            if ($token_arr === null) {
                throw new VisibleSystemException("token_arr cannot be null");
            }
            if (!is_array($token_arr)) {
                $debug = var_export($token_arr, true);
                throw new VisibleSystemException("token_arr is not an array: $debug");
            }
            foreach ($token_arr as $key => $info) {
                if (!is_numeric($info)) {
                    throw new VisibleSystemException("token_arr item is not a number");
                }
            }
            return count($token_arr);
        } catch (VisibleSystemException $e) {
            if ($bThrow) {
                throw $e;
            }
            return false;
        }
    }

    final function checkArrayOfIds($token_arr, $like = false, $bThrow = true) {
        try {
            if ($token_arr === null) {
                throw new VisibleSystemException("token_arr cannot be null");
            }
            if (!is_array($token_arr)) {
                $debug = var_export($token_arr, true);
                throw new VisibleSystemException("token_arr is not an array: $debug");
            }
            foreach ($token_arr as $key => $info) {
                $this->checkKey($key, false);
                $this->checkKey($info, $like);
            }
            return count($token_arr);
        } catch (VisibleSystemException $e) {
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
}
