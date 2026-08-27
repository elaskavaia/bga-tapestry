<?php

declare(strict_types=1);

require_once __DIR__ . "/../../tapestry.game.php";
//require_once "TokensInMem.php";

define("PCOLOR", "ff0000");
define("BCOLOR", "0000ff");

class GameUT extends Tapestry {
    protected $timachine;
    protected $xtable;
    public $curid;

    function __construct() {
        parent::__construct();
        include __DIR__ . "/../../material.inc.php";
        include __DIR__ . "/../../states.inc.php";
        $this->gamestate->_setStates($machinestates);
        $this->xtable = [];
        $this->curid = 1;
        $this->_setCurrentPlayerId($this->curid);
    }

    function init() {
        //$this->createTokens();
        $this->gamestate->changeActivePlayer(1);
        $this->gamestate->jumpToState(2);
    }

    public function getCurrentPlayerColor(): string {
        return $this->getPlayerColorById($this->curid);
    }

    function loadPlayersBasicInfos() {
        $default_colors = [PCOLOR, BCOLOR];
        $values = [];
        $id = 1;
        foreach ($default_colors as $color) {
            $values[$id] = [
                "player_id" => $id,
                "player_color" => $color,
                "player_name" => "player$id",
                "player_zombie" => 0,
                "player_no" => $id,
                "player_eliminated" => 0,
            ];
            $id++;
        }
        return $values;
    }

    function getNewDeck(string $tableName): \Bga\GameFramework\Components\Deck {
        $res = new \Bga\GameFramework\Components\Deck();
        $res->init($tableName);
        return $res;
    }
}
