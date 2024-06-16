<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;

require_once "tapestry.game.php";
//require_once "TokensInMem.php";


define("PCOLOR", "ff0000");
define("BCOLOR", "0000ff");

class GameUT extends Tapestry {
    protected $timachine;
    protected $xtable;
    public $curid;

    function __construct() {
        parent::__construct();
        include "./material.inc.php";
        include "./states.inc.php";
        $this->gamestate = new GameState($machinestates);
        $this->xtable = [];
        $this->curid = 1;
    }

    function init() {
        //$this->createTokens();
        $this->gamestate->changeActivePlayer(1);
        $this->gamestate->jumpToState(2);
    }



    public function getCurrentPlayerId($bReturnNullIfNotLogged = false) {
        return $this->curid;
    }


    protected function getCurrentPlayerColor() {
        return $this->getPlayerColorById($this->curid);
    }

    function loadPlayersBasicInfos() {
        $default_colors = array(PCOLOR, BCOLOR);
        $values = array();
        $id = 1;
        foreach ($default_colors as $color) {
            $values[$id] = array('player_id' => $id, 'player_color' => $color, 'player_name' => "player$id", 'player_zombie' => 0, 'player_no' => $id, 'player_eliminated' => 0);
            $id++;
        }
        return $values;
    }

    function getNew($modname): object {
        if ($modname == "module.common.deck") return new Deck();
        return null;
    }
}
final class GameTest extends TestCase {
    public $game;

    protected function setUp(): void
    {
     $this->game = $this->game();
    }

    private function game() {
        $m = new GameUT();
        $m->init();
        return $m;
    }

    public function testGameProgression() {
        $m = $this->game;

        $this->assertNotNull($m);
        $this->assertEquals(0, $m->getGameProgression());
    }

    function testMaterial() {
        $m = $this->game;
        //print("id,name, description\n");
        $m->doAdjustMaterial(2, 8);
        ksort($m->civilizations, SORT_NUMERIC);
        foreach ($m->civilizations as $civ => $civ_data) {
            $description = $civ_data['description'];
            if (is_array($description)) {
                $description = implode("\n", $description);
            }
            $name = $civ_data['name'];
            $al = array_get($civ_data, 'al', 4);
            $inst = $m->getCivilizationInstance($civ, false);
            $this->assertEquals($civ, $inst->getType());

            if ($al != 8) print("$name ($civ) \n$description\n\n");
        }
    }

    function testHistorians() {
        $m = $this->game;
        $civ = CIV_HISTORIANS;
        $m->doAdjustMaterial(2, 8);
        $inst = $m->getCivilizationInstance($civ, true);
        $income_trigger = $inst->getRules('income_trigger', null);
        $this->assertNotNull($income_trigger);
        $from = array_get($income_trigger, 'from', 0);
        $to = array_get($income_trigger, 'to', 0);
        $this->assertEquals($from, 2);
        $this->assertEquals($to, 5);

        $m->doAdjustMaterial(4, 8);
        $income_trigger = $inst->getRules('income_trigger', null);
        $this->assertNotNull($income_trigger);
        $from = array_get($income_trigger, 'from', 0);
        $to = array_get($income_trigger, 'to', 0);
        $this->assertEquals($from, 1);
        $this->assertEquals($to, 4);
    }

    function testCraftsmen() {
        $m = $this->game;
        $civ = CIV_CRAFTSMEN;
        $m->doAdjustMaterial(2, 8);
        $inst = $m->getCivilizationInstance($civ, true);
        $income_trigger = $inst->getRules('income_trigger', null);
        $this->assertNull($income_trigger);
        $mg= $inst->getRules('midgame_setup', null);
        $this->assertNotNull($mg);
    }

    function testCollectors() {
        $game = $this->game;
        $civ = CIV_COLLECTORS;
        $xciv = $game->getRulesBenefit(BE_COLLECTORS_GRAB, 'civ', null);
        $this->assertEquals($civ, $xciv);
        $xciv = $game->getRulesBenefit(BE_COLLECTORS_CARD, 'civ', null);
        $this->assertEquals($civ, $xciv);
        $inst = $game->getCivilizationInstance($civ, true);
        $this->assertNotNull($inst);
    }

    function testInfiltrators() {
        $game = $this->game;
        $civ = CIV_INFILTRATORS;
        $xciv = $game->getRulesBenefit(170, 'civ', null);
        $this->assertEquals($civ, $xciv);
        $xciv = $game->getRulesBenefit(171, 'civ', null);
        $this->assertEquals($civ, $xciv);
        $inst = $game->getCivilizationInstance($civ, true);
        $this->assertNotNull($inst);
    }
}
