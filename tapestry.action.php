<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * tapestry implementation : © Adam Dewbery <adam@dewbs.co.uk>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * tapestry.action.php
 *
 * Tapestry main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/tapestry/tapestry/myAction.html", ...)
 *
 */


class action_tapestry extends APP_GameAction
{
    // Constructor: please do not modify
    public function __default()
    {
        if ($this->isArg('notifwindow')) {
            $this->view = "common_notifwindow";
            $this->viewArgs['table'] = $this->getArg("table", AT_posint, true);
        } else {
            $this->view = "tapestry_tapestry";
            $this->trace("Complete reinitialization of board game");
        }
    }

    public function chooseCivilization()
    {
        $this->setAjaxMode();
        $civ = $this->getArg("civ", AT_posint, true);
        $cap = $this->getArg("cap", AT_posint, true);
        $this->game->chooseCivilization($civ, $cap);
        $this->ajaxResponse();
    }

    public function takeIncome()
    {
        $this->setAjaxMode();
        $this->game->takeIncome();
        $this->ajaxResponse();
    }
    
    public function unblock()
    {
        $this->setAjaxMode();
        $this->game->action_unblock();
        $this->ajaxResponse();
    }
    
    public function actionEliminate()
    {
        $this->setAjaxMode();
        $this->game->actionEliminate();
        $this->ajaxResponse();
    }

    public function alchemistRoll()
    {
        $this->setAjaxMode();
        $this->game->alchemistRoll();
        $this->ajaxResponse();
    }

    public function alchemistClaim()
    {
        $this->setAjaxMode();
        $this->game->alchemistClaim();
        $this->ajaxResponse();
    }

    public function advance()
    {
        $this->setAjaxMode();
        $track = $this->getArg("track", AT_posint, true);
        $spot = $this->getArg("spot", AT_posint, true);
        $payment = $this->getArg("payment", AT_numberlist, true);
        $order = $this->getArg("order", AT_numberlist, false, '');
        $this->game->action_advance($track, $spot, explode(',', $payment), explode(',', $order));
        $this->ajaxResponse();
    }

    public function mystic()
    {
        $this->setAjaxMode();
        $ids = $this->getArg("ids", AT_numberlist, true);
        $this->game->mystic(explode(',', $ids));
        $this->ajaxResponse();
    }


    public function selectTrackSpot()
    {
        $this->setAjaxMode();
        $track = $this->getArg("track", AT_posint, true);
        $spot = $this->getArg("spot", AT_posint, true);
        $cube_id = $this->getArg("cube", AT_posint, false, 0);
        $this->game->action_selectTrackSpot($track, $spot, $cube_id);
        $this->ajaxResponse();
    }

    public function select_cube()
    {
        $this->setAjaxMode();
        $cube_id = $this->getArg("cube", AT_posint, true);
        $this->game->action_selectCube($cube_id);
        $this->ajaxResponse();
    }

    public function choose_resources()
    {
        $this->setAjaxMode();
        $resources = $this->getArg("resources", AT_numberlist, true);
        $type = $this->getArg("type", AT_posint, true);
        $this->game->choose_resources(explode(',', $resources), $type);
        $this->ajaxResponse();
    }

    public function civTokenAdvance()
    {
        $this->setAjaxMode();
        $cid = $this->getArg("cid", AT_posint, true);
        $spot = $this->getArg("spot", AT_posint, true);
        $extra = $this->getArg("extra", AT_alphanum_dash, false, 0);
        $this->game->action_civTokenAdvance($cid, $spot, $extra);
        $this->ajaxResponse();
    }

    public function civTokenExtra()
    {
        $this->setAjaxMode();
        $cid = $this->getArg("cid", AT_posint, true);
        $spot = $this->getArg("spot", AT_posint, true);
        $extra = $this->getArg("extra", AT_json, false, []);
        $this->game->action_civTokenAdvance($cid, $spot, $extra);
        $this->ajaxResponse();
    }

    public function invent()
    {
        $this->setAjaxMode();
        $id = $this->getArg("id", AT_int, true);
        $this->game->action_invent($id);
        $this->ajaxResponse();
    }

    public function upgrade()
    {
        $this->setAjaxMode();
        $id = $this->getArg("id", AT_posint, true);
        $this->game->action_upgrade($id);
        $this->ajaxResponse();
    }

    public function explore()
    {
        $this->setAjaxMode();
   
        $location = $this->getArg("location", AT_alphanum_dash, true);
        $tile_id = $this->getArg("tid", AT_posint, true);
        $rot = $this->getArg("rot", AT_posint, true);

        $extra = [];
        $extra['militarism'] =  $this->getArg("militarism", AT_bool, false, false);
        $extra['exploitation'] = $this->getArg("exploitation", AT_bool, false, false);
        $extra['outpost_id'] = $this->getArg("outpost_id", AT_posint, false, 0);     
        $this->game->action_explore($location, $tile_id, $rot, $extra);
        $this->ajaxResponse();
    }

    public function conquer()
    {
        $this->setAjaxMode();
        $u = $this->getArg("U", AT_int, true);
        $v = $this->getArg("V", AT_int, true);
        $isol = $this->getArg("isol", AT_bool, true);
        $oid = $this->getArg("outpost", AT_int, true);
        
        $this->game->action_conquer($u, $v, $isol, $oid);
        $this->ajaxResponse();
    }

    public function colonialism()
    {
        $this->setAjaxMode();
        $u = $this->getArg("U", AT_int, true);
        $v = $this->getArg("V", AT_int, true);
        $this->game->colonialism($u, $v);
        $this->ajaxResponse();
    }


    public function alchemistChoice()
    {
        $this->setAjaxMode();
        $track = $this->getArg("track", AT_int, true);
        $this->game->action_alchemistChoice($track);
        $this->ajaxResponse();
    }

    public function conquer_structure()
    {
        $this->setAjaxMode();
        $u = $this->getArg("u", AT_int, true);
        $v = $this->getArg("v", AT_int, true);
        $this->game->conquer_structure($u, $v);
        $this->ajaxResponse();
    }

    public function standup()
    {
        $this->setAjaxMode();
        $ids = $this->getArg("outposts", AT_numberlist, true);
        $arr = $ids!==''?explode(',', $ids):[];
        $this->game->action_standup($arr);
        $this->ajaxResponse();
    }

    public function choose_die()
    {
        $this->setAjaxMode();
        $die = $this->getArg("die", AT_posint, true);
        $this->game->action_choose_die($die);
        $this->ajaxResponse();
    }

    public function selectIncomeBuilding()
    {
        $this->setAjaxMode();
        $type = $this->getArg("type", AT_posint, true);
        $this->game->selectIncomeBuilding($type);
        $this->ajaxResponse();
    }

    public function selectLandmark()
    {
        $this->setAjaxMode();
        $type = $this->getArg("type", AT_posint, true);
        $this->game->selectLandmark($type);
        $this->ajaxResponse();
    }

    public function research_decision()
    {
        $this->setAjaxMode();
        $decision = $this->getArg("decision", AT_posint, true);
        $spot = $this->getArg("spot", AT_posint, true);
        $this->game->action_research_decision($decision, $spot);
        $this->ajaxResponse();
    }

    public function choose_benefit()
    {
        $this->setAjaxMode();
        $bid = $this->getArg("bid", AT_posint, true);
        $spot = $this->getArg("spot", AT_int, true);
        $this->game->action_choose_benefit($bid,$spot);
        $this->ajaxResponse();
    }

    public function first_benefit()
    {
        $this->setAjaxMode();
        $bid = $this->getArg("bid", AT_posint, true);
        $spot = $this->getArg("spot", AT_int, true);
        $this->game->action_first_benefit($bid,$spot);
        $this->ajaxResponse();
    }

    public function techBenefit()
    {
        $this->setAjaxMode();
        $id = $this->getArg("id", AT_posint, true);
        $this->game->action_techBenefit($id);
        $this->ajaxResponse();
    }

    public function place_structure()
    {
        $this->setAjaxMode();
        $rot = $this->getArg("rot", AT_posint, true);
        $x = $this->getArg("x", AT_posint, true);
        $y = $this->getArg("y", AT_posint, true);
        $this->game->place_structure($rot, $x, $y);
        $this->ajaxResponse();
    }

    public function acdebug()
    {
        $this->setAjaxMode();
        $args = $this->getArg("a", AT_json, true);
        $this->game->action_debug($args);
        $this->ajaxResponse();
    }


    public function placeCraftsmen()
    {
        $this->setAjaxMode();
        $slot = $this->getArg("slot", AT_posint, true);
        $this->game->placeCraftsmen($slot);
        $this->ajaxResponse();
    }

    public function playCard()
    {
        $this->setAjaxMode();
        $card_id = $this->getArg("card_id", AT_posint, true);
        $this->game->action_playTapestryCard($card_id);
        $this->ajaxResponse();
    }

    public function sendHistorian()
    {
        $this->setAjaxMode();
        $pid = $this->getArg("pid", AT_posint, true);
        $tid = $this->getArg("tid", AT_posint, true);
        $token_id = $this->getArg("token_id", AT_posint, true);
        $this->game->sendHistorian($pid, $tid, $token_id);
        $this->ajaxResponse();
    }

    public function ageOfSail()
    {
        $this->setAjaxMode();
        $pid = $this->getArg("pid", AT_posint, true);
        $tid = $this->getArg("tid", AT_posint, true);
        $this->game->ageOfSail($pid, $tid);
        $this->ajaxResponse();
    }
    public function sendInventor()
    {
        $this->setAjaxMode();
        $id = $this->getArg("id", AT_posint, true);
        $this->game->sendInventor($id);
        $this->ajaxResponse();
    }

    public function tapestryChoice()
    {
        $this->setAjaxMode();
        $card_id = $this->getArg("card_id", AT_posint, true);
        $this->game->tapestryChoice($card_id);
        $this->ajaxResponse();
    }

    public function decline()
    {
        $this->setAjaxMode();
        $this->game->action_decline();
        $this->ajaxResponse();
    }

    public function actionUndo()
    {
        $this->setAjaxMode();
        $this->game->actionUndo();
        $this->ajaxResponse();
    }

    public function actionConfirm()
    {
        $this->setAjaxMode();
        $this->game->actionConfirm();
        $this->ajaxResponse();
    }

    public function civDecline()
    {
        $this->setAjaxMode();
        $civ = $this->getArg("cid", AT_posint, true);
        $this->game->action_civDecline($civ);
        $this->ajaxResponse();
    }

    public function decline_tapestry()
    {
        $this->setAjaxMode();
        $this->game->decline_tapestry();
        $this->ajaxResponse();
    }

    public function declineBonus()
    {
        $this->setAjaxMode();
        $this->game->declineBonus();
        $this->ajaxResponse();
    }

    public function acceptBonus()
    {
        $this->setAjaxMode();
        $ids = $this->getArg("ids", AT_numberlist, true);
        $dest = $this->getArg("dest", AT_int, false, 0);
        $this->game->action_acceptBonus($ids?explode(',', $ids):[], $dest);
        $this->ajaxResponse();
    }

    public function trap()
    {
        $this->setAjaxMode();
        $card_id = $this->getArg("card_id", AT_posint, true);
        $this->game->trap($card_id);
        $this->ajaxResponse();
    }

    public function formAlliance()
    {
        $this->setAjaxMode();
        $pid = $this->getArg("pid", AT_posint, true);
        $this->game->formAlliance($pid);
        $this->ajaxResponse();
    }

    public function decline_trap()
    {
        $this->setAjaxMode();
        $this->game->decline_trap();
        $this->ajaxResponse();
    }

    public function explore_space()
    {
        $this->setAjaxMode();
        $sid = $this->getArg("sid", AT_posint, true);
        $this->game->action_exploreSpace($sid);
        $this->ajaxResponse();
    }

    public function moveStructureOnto()
    {
        $this->setAjaxMode();
        $location = $this->getArg("location", AT_alphanum_dash, true);
        $id = $this->getArg("id", AT_int, false, 0);
        $this->game->action_moveStructureOnto($location, $id);
        $this->ajaxResponse();
    }
    public function keepCard()
    {
        $this->setAjaxMode();
        $ids = $this->getArg("ids", AT_numberlist, true);
        $dest = $this->getArg("dest", AT_int, false, 0);
        $ids_arr = $ids?explode(',', $ids):[];
        $this->game->action_keepCard($ids_arr, $dest);
        $this->ajaxResponse();
    }
    
    public function activatedAbility()
    {
        $this->setAjaxMode();
        $ability = $this->getArg("ability", AT_alphanum_dash, true);
        $arg = $this->getArg("arg", AT_alphanum_dash, false, null);
        /** @var PGameXBody */
        $game = $this->game;
        $game->action_activatedAbility($ability, $arg);
        $this->ajaxResponse();
    }
}
